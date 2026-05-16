<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Product;
use App\Models\Notification;
use App\Models\BankDetail;
use App\Mail\SubscriptionMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class SubscriptionController extends Controller
{
    public function store(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret') ?? env('STRIPE_SECRET'));
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'nb_parts' => 'required|numeric|min:0.0001',
            'moyen_paiement' => 'required|string',
        ]);

        $product = Product::find($request->product_id);
        $montant_total = $request->nb_parts * $product->vl;
        $final_amount = $montant_total + ($montant_total * 0.01);

        if ($montant_total < $product->seuil_minimum) {
            return response()->json(['message' => 'Le montant total est inférieur au seuil minimum.'], 422);
        }

        $subscription = Subscription::create([
            'user_id' => Auth::id(),
            'product_id' => $product->id,
            'nb_parts' => $request->nb_parts,
            'prix_unitaire' => $product->vl,
            'montant_total' => $montant_total,
            'moyen_paiement' => $request->moyen_paiement,
            'statut' => 'En attente',
            'reference_transaction' => 'FCP-' . strtoupper(bin2hex(random_bytes(4))),
        ]);

        $clientSecret = null;
        $pekBankDetails = null;

        // 2. Handle Payment Logic
        if ($request->moyen_paiement === 'bank_card') {
            // Stripe Card
            try {
                $user = Auth::user();
                $customer = \Stripe\Customer::create([
                    'email' => $user->email,
                    'name' => "{$user->first_name} {$user->last_name}",
                ]);

                $paymentIntent = PaymentIntent::create([
                    'amount' => (int)($final_amount * 100), // Stripe en centimes
                    'currency' => 'eur',
                    'customer' => $customer->id,
                    'payment_method_types' => ['card'],
                    'metadata' => [
                        'subscription_id' => $subscription->id,
                        'reference' => $subscription->reference_transaction
                    ],
                ]);
                $clientSecret = $paymentIntent->client_secret;
            } catch (\Exception $e) {
                \Log::error("Stripe Error: " . $e->getMessage());
            }
        } elseif (in_array($request->moyen_paiement, ['orange_money', 'mtn_momo'])) {
            // CoolPay Mobile Money (CURL Version)
            try {
                $user = Auth::user();
                $coolpayPublicKey = env('COOLPAY_PUBLIC_KEY');
                
                $fields = [
                    'transaction_amount' => (env('APP_ENV') === 'production') ? (int)$final_amount : 100,
                    'transaction_currency' => 'XAF',
                    'transaction_reason' => "Souscription PEK: {$product->libelle}",
                    'app_transaction_ref' => $subscription->reference_transaction,
                    'customer_phone_number' => $request->phone_number ?? $user->phone,
                    'customer_name' => "{$user->first_name} {$user->last_name}",
                    'customer_email' => $user->email,
                    'customer_lang' => 'fr'
                ];

                $logFields = $fields;
                $logFields['customer_phone_number'] = substr($fields['customer_phone_number'], 0, 4) . '****' . substr($fields['customer_phone_number'], -2);
                $logFields['customer_email'] = substr($fields['customer_email'], 0, 3) . '****@' . explode('@', $fields['customer_email'])[1];

                \Log::info("CoolPay Request to " . (env('APP_ENV') === 'production' ? 'PROD' : 'SANDBOX') . ": ", $logFields);

                $curl = curl_init();
                curl_setopt_array($curl, array(
                  CURLOPT_URL => "https://my-coolpay.com/api/{$coolpayPublicKey}/payin",
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_ENCODING => '',
                  CURLOPT_MAXREDIRS => 10,
                  CURLOPT_TIMEOUT => 0,
                  CURLOPT_FOLLOWLOCATION => true,
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
                  CURLOPT_CUSTOMREQUEST => 'POST',
                  CURLOPT_POSTFIELDS => json_encode($fields),
                  CURLOPT_SSL_VERIFYPEER => env('APP_ENV') === 'production', 
                  CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Expect:'
                  ),
                ));

                $response = curl_exec($curl);
                $err = curl_error($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);

                if ($err) {
                    \Log::error("CoolPay CURL Error: " . $err);
                    return response()->json(['error' => "Erreur de connexion à CoolPay: $err"], 500);
                } else {
                    $coolpayData = json_decode($response, true);
                    \Log::info("CoolPay Response: ", $coolpayData ?? []);
                    
                    if ($httpCode >= 400 || (isset($coolpayData['status']) && $coolpayData['status'] === 'error')) {
                        return response()->json([
                            'error' => 'Paiement refusé par l\'opérateur',
                            'details' => $coolpayData
                        ], 400);
                    }
                    
                    // On ne renvoie que le strict nécessaire
                    $clientSecret = [
                        'payment_status' => $coolpayData['status'] ?? 'pending',
                        'ussd_code' => $coolpayData['ussd'] ?? null,
                        'transaction_ref' => $coolpayData['transaction_ref'] ?? null
                    ]; 
                }
            } catch (\Exception $e) {
                \Log::error("CoolPay Exception: " . $e->getMessage());
                return response()->json(['error' => $e->getMessage()], 500);
            }
        } elseif ($request->moyen_paiement === 'stripe') {
            // Manual Virement (using DB settings)
            $bank = BankDetail::where('is_active', true)->first();
            $pekBankDetails = [
                'bank_name' => $bank ? $bank->bank_name : 'Banque Atlantique',
                'iban' => $bank ? $bank->iban : env('PEK_BANK_IBAN'),
                'rib' => $bank ? $bank->rib : env('PEK_BANK_RIB'),
                'swift' => $bank ? $bank->swift : env('PEK_BANK_SWIFT'),
            ];
        }

        // 3. Create In-App Notification
        Notification::create([
            'user_id' => Auth::id(),
            'title' => 'Souscription enregistrée',
            'body' => "Votre demande de souscription pour {$product->libelle} ({$subscription->reference_transaction}) est en attente.",
            'type' => 'subscription'
        ]);

        // 4. Dispatch Background Job (Emails + PDF)
        \App\Jobs\ProcessSubscriptionReceipt::dispatch($subscription);

        return response()->json([
            'message' => 'Souscription enregistrée.',
            'subscription' => $subscription,
            'client_secret' => $clientSecret,
            'pek_bank_details' => $pekBankDetails,
        ]);
    }

    public function index()
    {
        return response()->json(Auth::user()->subscriptions()->with('product')->get());
    }
}
