<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Subscription;
use App\Models\Notification;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\PaymentIntent;

class WebhookController extends Controller
{
    public function handleStripe(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        } catch(\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type === 'payment_intent.succeeded') {
            $paymentIntent = $event->data->object;
            $subscriptionId = $paymentIntent->metadata->subscription_id;

            $subscription = Subscription::find($subscriptionId);
            if ($subscription) {
                $subscription->update(['statut' => 'Succès']);

                Notification::create([
                    'user_id' => $subscription->user_id,
                    'title' => 'Paiement Confirmé ✅',
                    'body' => "Votre souscription au fonds {$subscription->product->libelle} a été validée avec succès.",
                    'type' => 'success'
                ]);
            }
        }

        return response()->json(['status' => 'success']);
    }

    public function handleCoolPay(Request $request)
    {
        // Sécurité : Vérification du jeton secret dans l'URL
        if ($request->query('token') !== env('COOLPAY_WEBHOOK_TOKEN')) {
            \Log::warning("Unauthorized CoolPay Webhook Attempt from IP: " . $request->ip());
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        \Log::info("CoolPay Webhook Received (Authorized)");

        $status = $request->input('status');
        $appRef = $request->input('app_transaction_ref');

        if (strtolower($status) === 'success' || strtolower($status) === 'successful') {
            $subscription = Subscription::where('reference_transaction', $appRef)->first();
            
            if ($subscription && $subscription->statut !== 'Succès') {
                $subscription->update(['statut' => 'Succès']);

                Notification::create([
                    'user_id' => $subscription->user_id,
                    'title' => 'Paiement Confirmé ✅',
                    'body' => "Votre paiement pour {$subscription->product->libelle} a été validé. Vos parts ont été créditées.",
                    'type' => 'success'
                ]);

                \Log::info("Subscription {$appRef} marked as SUCCESS via Webhook.");
            }
        }

        return response()->json(['status' => 'received']);
    }
}
