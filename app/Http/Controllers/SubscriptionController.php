<?php

namespace App\Http\Controllers;

use App\Models\BankDetail;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\Payments\LocalPaymentGateway;
use App\Services\Payments\PaymentCheckoutGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PaymentCheckoutGateway $paymentGateway,
        private readonly LocalPaymentGateway $localPaymentGateway,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key')]);
        if (is_string($request->input('payment_phone'))) {
            $rawPhone = preg_replace('/[\s()+-]/', '', $request->input('payment_phone'));
            if (str_starts_with($rawPhone, '00237')) $rawPhone = substr($rawPhone, 2);
            if (strlen($rawPhone) === 9 && str_starts_with($rawPhone, '6')) {
                $rawPhone = '237'.$rawPhone;
            }
            $request->merge(['payment_phone' => $rawPhone]);
        }
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'nb_parts' => ['required_without:investment_amount', 'numeric', 'min:0.0001', 'max:99999999'],
            'investment_amount' => ['sometimes', 'required', 'integer', 'min:1', 'max:'.config('payments.max_investment')],
            'bank_detail_id' => ['sometimes', 'nullable', 'integer', Rule::exists('bank_details', 'id')->where('is_active', true)],
            'moyen_paiement' => ['required', Rule::in(['card', 'mobile_money', 'orange_money', 'mtn_momo', 'bank_transfer'])],
            'payment_phone' => ['required_if:moyen_paiement,orange_money,mtn_momo', 'nullable', 'string', 'regex:/^2376[0-9]{8}$/'],
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);

        $user = $request->user()->loadMissing('onboardingSession');
        if ($user->onboarding_status !== 'validated') {
            return response()->json([
                'message' => 'Votre dossier KYC doit être validé avant toute souscription.',
            ], 403);
        }

        if (in_array($validated['moyen_paiement'], ['orange_money', 'mtn_momo'], true)) {
            abort_unless(app(\App\Services\Payments\S3pGateway::class)->available($validated['moyen_paiement']), 503, 'Cet opérateur n’est pas encore activé.');
        }

        try {
            [$subscription, $created] = DB::transaction(function () use ($user, $validated) {
                $existing = Subscription::query()
                    ->where('user_id', $user->id)
                    ->where('idempotency_key', $validated['idempotency_key'])
                    ->first();

                if ($existing) {
                    $sameRequest = $existing->product_id === (int) $validated['product_id']
                        && (isset($validated['investment_amount'])
                            ? (int) $existing->investment_amount === (int) $validated['investment_amount']
                            : abs((float) $existing->nb_parts - round((float) $validated['nb_parts'], 8)) < 0.00000001)
                        && $existing->moyen_paiement === $validated['moyen_paiement']
                        && ($existing->payment_phone ?? null) === ($validated['payment_phone'] ?? null)
                        && (!isset($validated['bank_detail_id']) || (int) ($existing->bank_snapshot['id'] ?? 0) === (int) $validated['bank_detail_id']);

                    if (! $sameRequest) {
                        abort(409, 'Cette clé d’idempotence est déjà associée à une autre souscription.');
                    }

                    return [$existing->load('product'), false];
                }

                $product = Product::query()->lockForUpdate()->findOrFail($validated['product_id']);
                if (! $product->is_active) {
                    abort(422, 'Ce produit n’est pas disponible à la souscription car il est désactivé.');
                }

                $unitPrice = round((float) $product->vl, 4);
                abort_unless($unitPrice > 0, 422, 'Valeur liquidative indisponible.');
                $parts = isset($validated['investment_amount'])
                    ? (float) \Brick\Math\BigDecimal::of((string) $validated['investment_amount'])->dividedBy((string) $product->vl, 8, \Brick\Math\RoundingMode::DOWN)->__toString()
                    : round((float) $validated['nb_parts'], 8);
                // XAF is a zero-decimal currency: every payable amount must be a whole FCFA.
                $subtotal = isset($validated['investment_amount']) ? (int) $validated['investment_amount'] : (int) round($parts * $unitPrice);
                abort_if($subtotal > config('payments.max_investment'), 422, 'Montant maximum dépassé.');
                $minimum = max((float) $product->seuil_minimum, $unitPrice);

                if ($parts < 1 || $subtotal + 0.001 < $minimum) {
                    abort(422, 'Le seuil minimum de souscription de '.number_format($minimum, 0, ',', ' ').' FCFA n’est pas atteint.');
                }

                $fees = intdiv($subtotal * (int) config('payments.fee_basis_points') + 5000, 10000);
                $total = $subtotal + $fees;

                $bank = $validated['moyen_paiement'] === 'bank_transfer' ? BankDetail::where('is_active', true)
                    ->when(isset($validated['bank_detail_id']), fn ($q) => $q->whereKey($validated['bank_detail_id']))->first() : null;
                if ($validated['moyen_paiement'] === 'bank_transfer') abort_unless($bank && ($bank->rib || $bank->iban), 422, 'Coordonnées bancaires indisponibles.');

                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'nb_parts' => $parts,
                    'prix_unitaire' => $unitPrice,
                    'montant_total' => $total,
                    'moyen_paiement' => $validated['moyen_paiement'],
                    'statut' => 'En attente',
                    'reference_transaction' => 'FCP-'.strtoupper(bin2hex(random_bytes(8))),
                    'idempotency_key' => $validated['idempotency_key'],
                    'payment_currency' => 'XAF',
                ]);
                $subscription->forceFill([
                    'investment_amount' => $subtotal, 'subscription_fee' => $fees,
                    'bank_snapshot' => $bank?->only(['id', 'bank_name', 'beneficiary', 'iban', 'rib', 'swift', 'bank_instructions']),
                    'mobile_provider' => in_array($validated['moyen_paiement'], ['orange_money', 'mtn_momo'], true) ? $validated['moyen_paiement'] : null,
                    'payment_phone' => $validated['payment_phone'] ?? null,
                ])->save();

                Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Demande de souscription enregistrée',
                    'body' => match ($subscription->moyen_paiement) {
                        'card' => "Votre demande {$subscription->reference_transaction} attend la finalisation du paiement par carte.",
                        'mobile_money', 'orange_money', 'mtn_momo' => "Votre demande {$subscription->reference_transaction} attend la finalisation du paiement Mobile Money.",
                        default => "Votre demande {$subscription->reference_transaction} attend la réception et le rapprochement du virement.",
                    },
                    'type' => 'subscription',
                ]);

                return [$subscription->load('product'), true];
            });

            if ($subscription->moyen_paiement === 'card') {
                return $this->initiateStripeCheckout($subscription, $created ? 201 : 200);
            }

            if ($subscription->mobile_provider) {
                return $this->s3pResponse(app(\App\Services\Payments\MobilePaymentService::class)->start($subscription), $created ? 201 : 200);
            }
            if ($subscription->moyen_paiement === 'mobile_money') {
                return $this->initiateEnkapCheckout($subscription, $created ? 201 : 200);
            }

            return response()->json([
                'message' => $created
                    ? 'Demande de souscription enregistrée.'
                    : 'Cette demande avait déjà été enregistrée.',
                'subscription' => $subscription,
                'pek_bank_details' => $subscription->bank_snapshot ?? BankDetail::where('is_active', true)->first(),
            ], $created ? 201 : 200);
        } catch (HttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        }
    }

    public function startPayment(Request $request, int $id): JsonResponse
    {
        $subscription = $request->user()->subscriptions()->with(['user', 'product'])->findOrFail($id);
        if ($subscription->mobile_provider) return $this->s3pResponse(app(\App\Services\Payments\MobilePaymentService::class)->start($subscription));

        if ($subscription->statut === 'Succès') {
            return response()->json([
                'message' => 'Ce paiement est déjà confirmé.',
                'subscription' => $subscription,
                'payment' => ['status' => 'paid', 'redirect_required' => false],
            ]);
        }

        return match ($subscription->moyen_paiement) {
            'card' => $this->initiateStripeCheckout($subscription),
            'mobile_money', 'orange_money', 'mtn_momo' => $this->initiateEnkapCheckout($subscription),
            default => response()->json([
                'message' => 'Cette souscription doit être réglée par virement bancaire.',
            ], 422),
        };
    }

    public function paymentStatus(Request $request, int $id): JsonResponse
    {
        $subscription = $request->user()->subscriptions()->with('product')->findOrFail($id);

        return $this->paymentStatusResponse($subscription);
    }

    public function paymentStatusByReference(Request $request, string $reference): JsonResponse
    {
        $subscription = $request->user()->subscriptions()
            ->with('product')
            ->where('enkap_merchant_reference', $reference)
            ->firstOrFail();

        return $this->paymentStatusResponse($subscription);
    }

    public function stripeCheckoutReturn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:255', 'regex:/^cs_(?:test|live)_[A-Za-z0-9_]+$/'],
        ]);

        $sessionId = $validated['session_id'];
        $subscription = Subscription::query()
            ->where('moyen_paiement', 'card')
            ->where('stripe_checkout_session_id', $sessionId)
            ->first();

        if (! $subscription) {
            return response()->json(['message' => 'Session de paiement introuvable.'], 404);
        }

        if ($subscription->statut === 'Succès') {
            return $this->stripeCheckoutReturnResponse($subscription);
        }

        try {
            // L'identifiant reçu du navigateur n'est jamais une preuve de paiement.
            // Le statut, le montant, la devise et les métadonnées sont relus chez Stripe.
            $state = $this->paymentGateway->retrieveSession($sessionId);
            $this->synchronizeStripeState($subscription, $state);
            $subscription->refresh();
        } catch (HttpException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Stripe Checkout return verification failed', [
                'subscription_id' => $subscription->id,
                'exception' => $exception::class,
            ]);

            return $this->stripeCheckoutReturnResponse(
                $subscription,
                'La confirmation Stripe est momentanément indisponible. Elle sera reprise automatiquement.'
            );
        }

        return $this->stripeCheckoutReturnResponse($subscription);
    }

    private function paymentStatusResponse(Subscription $subscription): JsonResponse
    {
        if ($subscription->mobile_provider) {
            try {
                return $this->s3pResponse(app(\App\Services\Payments\MobilePaymentService::class)->refresh($subscription));
            } catch (\Throwable) {
                return response()->json(['message' => 'Vérification temporairement indisponible. Ne relancez pas de débit.', 'subscription' => $subscription->fresh()], 503);
            }
        }

        if ($subscription->moyen_paiement === 'card'
            && $subscription->statut !== 'Succès'
            && $subscription->stripe_checkout_session_id) {
            try {
                $state = $this->paymentGateway->retrieveSession($subscription->stripe_checkout_session_id);
                $this->synchronizeStripeState($subscription, $state);
                $subscription->refresh()->load('product');
            } catch (Throwable $exception) {
                Log::warning('Stripe Checkout status refresh failed', [
                    'subscription_id' => $subscription->id,
                    'exception' => $exception::class,
                ]);
            }
        } elseif ($this->isLocalPayment($subscription)
            && $subscription->statut !== 'Succès'
            && $subscription->maviance_transaction_ref) {
            try {
                $this->refreshEnkapSubscription($subscription);
                $subscription->refresh()->load('product');
            } catch (Throwable $exception) {
                Log::warning('e-nkap payment status refresh failed', [
                    'subscription_id' => $subscription->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        $status = match ($subscription->statut) {
            'Succès' => 'paid',
            'Échec' => 'failed',
            default => 'pending',
        };

        return response()->json([
            'status' => $status,
            'subscription' => $subscription,
            'message' => match ($status) {
                'paid' => 'Paiement confirmé. Vos parts sont créditées.',
                'failed' => 'Le paiement n’a pas abouti. Vous pouvez recommencer.',
                default => 'Le paiement est toujours en attente de confirmation.',
            },
        ]);
    }

    private function stripeCheckoutReturnResponse(Subscription $subscription, ?string $message = null): JsonResponse
    {
        $status = match ($subscription->statut) {
            'Succès' => 'paid',
            'Échec' => 'failed',
            default => 'pending',
        };

        return response()->json([
            'status' => $status,
            'message' => $message ?? match ($status) {
                'paid' => 'Paiement confirmé. Vos parts sont créditées.',
                'failed' => 'Le paiement n’a pas abouti. Vous pouvez recommencer.',
                default => 'Le paiement est toujours en attente de confirmation.',
            },
        ]);
    }

    public function checkMavianceStatus(Request $request, int $id): JsonResponse
    {
        $owned = $request->user()->subscriptions()->findOrFail($id);
        if ($owned->mobile_provider) return $this->paymentStatusResponse($owned);
        $subscription = $request->user()->subscriptions()->with('product')->findOrFail($id);

        if ($subscription->statut === 'Succès') {
            return response()->json([
                'status' => 'success',
                'subscription' => $subscription,
                'message' => 'Cette transaction est déjà confirmée.',
            ]);
        }

        if (! $this->isLocalPayment($subscription) || ! $subscription->maviance_transaction_ref) {
            return response()->json(['message' => 'Cette souscription ne possède pas de transaction e-nkap vérifiable.'], 422);
        }

        return $this->paymentStatusResponse($subscription);
    }

    public function handleEnkapNotification(Request $request, ?string $reference = null): JsonResponse
    {
        $reference = $reference ?: (string) $request->query('tx_id', '');
        if (! preg_match('/^[A-Za-z0-9._:-]{1,36}$/', $reference)) {
            return response()->json(['message' => 'Référence invalide.'], 422);
        }

        $subscription = Subscription::query()
            ->where('enkap_merchant_reference', $reference)
            ->first();
        if (! $subscription || ! $this->isLocalPayment($subscription)) {
            return response()->json(['message' => 'Souscription introuvable.'], 404);
        }

        try {
            // Le statut non signé transmis dans l’ITN e-nkap n’est pas une preuve de paiement.
            // La confirmation est toujours relue depuis l’API e-nkap authentifiée.
            $this->refreshEnkapSubscription($subscription);

            return response()->json(['status' => 'received']);
        } catch (Throwable $exception) {
            Log::error('e-nkap notification verification failed', [
                'subscription_id' => $subscription->id,
                'exception' => $exception::class,
            ]);

            return response()->json(['message' => 'Vérification du paiement impossible.'], 502);
        }
    }

    private function initiateEnkapCheckout(Subscription $subscription, int $successStatus = 200): JsonResponse
    {
        $subscription->loadMissing(['user', 'product']);

        try {
            if ($subscription->maviance_transaction_ref && $subscription->enkap_merchant_reference) {
                $state = $this->localPaymentGateway->retrieveOrder($subscription->maviance_transaction_ref);
                $this->assertEnkapOrderMatches($subscription, $state);
                $this->synchronizeEnkapState($subscription, $state);
                $subscription->refresh();

                if ($subscription->statut === 'Succès') {
                    return response()->json([
                        'message' => 'Paiement confirmé.',
                        'subscription' => $subscription->load('product'),
                        'payment' => ['status' => 'paid', 'redirect_required' => false],
                    ], $successStatus);
                }

                if ($this->isEnkapPending($state['status'])
                    && $this->isAllowedEnkapCheckoutUrl((string) $subscription->provider_checkout_url)) {
                    $state['url'] = (string) $subscription->provider_checkout_url;

                    return $this->enkapCheckoutResponse($subscription, $state, $successStatus);
                }

                DB::transaction(function () use ($subscription) {
                    $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
                    if ($locked->statut !== 'Succès') {
                        $locked->update([
                            'maviance_transaction_ref' => null,
                            'enkap_merchant_reference' => null,
                            'provider_checkout_url' => null,
                            'payment_attempt' => max(1, (int) $locked->payment_attempt) + 1,
                            'payment_expires_at' => null,
                            'statut' => 'En attente',
                        ]);
                    }
                });
                $subscription->refresh();
            }

            if ((int) $subscription->payment_attempt < 1) {
                $subscription->update(['payment_attempt' => 1]);
            }

            $state = $this->localPaymentGateway->createOrder(
                $subscription,
                (int) $subscription->payment_attempt,
            );
            $this->assertEnkapOrderMatches($subscription, $state);

            if (! $this->isAllowedEnkapCheckoutUrl($state['url'])) {
                throw new \RuntimeException('e-nkap a retourné une URL de paiement non autorisée.');
            }

            $subscription->update([
                'maviance_transaction_ref' => $state['id'],
                'enkap_merchant_reference' => $state['reference'],
                'provider_checkout_url' => $state['url'],
                'payment_initiated_at' => now(),
                'payment_expires_at' => $state['expires_at'] ? now()->setTimestamp($state['expires_at']) : null,
                'payment_currency' => 'XAF',
                'statut' => 'En attente',
            ]);

            return $this->enkapCheckoutResponse($subscription->fresh('product'), $state, $successStatus);
        } catch (HttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        } catch (Throwable $exception) {
            Log::error('e-nkap checkout initialization failed', [
                'subscription_id' => $subscription->id,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Le paiement Mobile Money n’a pas pu être initialisé. Votre demande est conservée et peut être reprise.',
                'subscription_id' => $subscription->id,
                'retryable' => true,
            ], 502);
        }
    }

    /** @param array{id: string, url: string, status: string, amount_total: int, currency: string, expires_at: int|null, reference: string, provider_name: string|null} $state */
    private function enkapCheckoutResponse(Subscription $subscription, array $state, int $status): JsonResponse
    {
        return response()->json([
            'message' => 'Souscription enregistrée. Finalisez maintenant le paiement Mobile Money.',
            'subscription' => $subscription,
            'payment' => [
                'provider' => 'enkap',
                'status' => 'pending',
                'redirect_required' => true,
                'checkout_url' => $state['url'],
                'expires_at' => $state['expires_at'],
            ],
        ], $status);
    }

    private function refreshEnkapSubscription(Subscription $subscription): void
    {
        $state = $this->localPaymentGateway->retrieveOrder((string) $subscription->maviance_transaction_ref);
        $this->assertEnkapOrderMatches($subscription, $state);
        $this->synchronizeEnkapState($subscription, $state);
    }

    /** @param array{id: string, url: string, status: string, amount_total: int, currency: string, expires_at: int|null, reference: string, provider_name: string|null} $state */
    private function synchronizeEnkapState(Subscription $subscription, array $state): void
    {
        $this->assertEnkapOrderMatches($subscription, $state);
        $status = strtoupper($state['status']);

        DB::transaction(function () use ($subscription, $state, $status) {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $this->assertEnkapOrderMatches($locked, $state);

            if ($status === 'CONFIRMED' && $locked->statut !== 'Succès') {
                $locked->update([
                    'statut' => 'Succès',
                    'payment_confirmed_at' => now(),
                    'payment_currency' => 'XAF',
                    'provider_checkout_url' => null,
                ]);
            } elseif (in_array($status, ['FAILED', 'CANCELED'], true) && $locked->statut !== 'Succès') {
                $locked->update([
                    'statut' => 'Échec',
                    'provider_checkout_url' => null,
                ]);
            }
        });
    }

    /** @param array{id: string, url: string, status: string, amount_total: int, currency: string, expires_at: int|null, reference: string, provider_name: string|null} $state */
    private function assertEnkapOrderMatches(Subscription $subscription, array $state): void
    {
        $matches = $state['id'] !== ''
            && ($subscription->maviance_transaction_ref === null
                || hash_equals($subscription->maviance_transaction_ref, $state['id']))
            && $state['reference'] !== ''
            && ($subscription->enkap_merchant_reference === null
                || hash_equals($subscription->enkap_merchant_reference, $state['reference']))
            && strtoupper($state['currency']) === 'XAF'
            && $state['amount_total'] === (int) round((float) $subscription->montant_total);

        if (! $matches) {
            Log::warning('e-nkap payment mismatch', ['subscription_id' => $subscription->id]);
            abort(409, 'La transaction e-nkap ne correspond pas à cette souscription.');
        }
    }

    private function isAllowedEnkapCheckoutUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return false;
        }

        return in_array(
            strtolower((string) parse_url($url, PHP_URL_HOST)),
            config('services.maviance.checkout_allowed_hosts', []),
            true,
        );
    }

    private function isLocalPayment(Subscription $subscription): bool
    {
        return in_array($subscription->moyen_paiement, ['mobile_money', 'orange_money', 'mtn_momo'], true);
    }

    private function isEnkapPending(string $status): bool
    {
        return in_array(strtoupper($status), ['CREATED', 'INITIALISED', 'IN_PROGRESS'], true);
    }

    private function initiateStripeCheckout(Subscription $subscription, int $successStatus = 200): JsonResponse
    {
        $subscription->loadMissing(['user', 'product']);

        try {
            if ($subscription->stripe_checkout_session_id) {
                $existingState = $this->paymentGateway->retrieveSession($subscription->stripe_checkout_session_id);
                $this->assertStripeCheckoutMatches($subscription, $existingState);
                $this->synchronizeStripeState($subscription, $existingState);
                $subscription->refresh();

                if ($subscription->statut === 'Succès') {
                    return response()->json([
                        'message' => 'Paiement confirmé.',
                        'subscription' => $subscription->load('product'),
                        'payment' => ['status' => 'paid', 'redirect_required' => false],
                    ], $successStatus);
                }

                if ($existingState['status'] === 'open'
                    && $existingState['payment_status'] === 'unpaid'
                    && $this->isAllowedCheckoutUrl($existingState['url'])) {
                    return $this->checkoutResponse($subscription, $existingState, $successStatus);
                }

                DB::transaction(function () use ($subscription) {
                    $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
                    if ($locked->statut !== 'Succès') {
                        $locked->update([
                            'stripe_checkout_session_id' => null,
                            'payment_attempt' => max(1, (int) $locked->payment_attempt) + 1,
                            'payment_expires_at' => null,
                            'statut' => 'En attente',
                        ]);
                    }
                });
                $subscription->refresh();
            }

            if ((int) $subscription->payment_attempt < 1) {
                $subscription->update(['payment_attempt' => 1]);
            }

            $state = $this->paymentGateway->createSession(
                $subscription,
                (int) $subscription->payment_attempt,
            );
            $this->assertStripeCheckoutMatches($subscription, $state);

            if (! $this->isAllowedCheckoutUrl($state['url'])) {
                throw new \RuntimeException('Stripe a retourné une URL de paiement non autorisée.');
            }

            $subscription->update([
                'stripe_checkout_session_id' => $state['id'],
                'payment_initiated_at' => now(),
                'payment_expires_at' => $state['expires_at'] ? now()->setTimestamp($state['expires_at']) : null,
                'payment_currency' => 'XAF',
                'statut' => 'En attente',
            ]);

            return $this->checkoutResponse($subscription->fresh('product'), $state, $successStatus);
        } catch (HttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        } catch (Throwable $exception) {
            Log::error('Stripe Checkout initialization failed', [
                'subscription_id' => $subscription->id,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Le paiement sécurisé n’a pas pu être initialisé. Votre demande est conservée et peut être reprise.',
                'subscription_id' => $subscription->id,
                'retryable' => true,
            ], 502);
        }
    }

    /** @param array{id: string, url: string, status: string, payment_status: string, amount_total: int, currency: string, expires_at: int|null, payment_intent_id: string|null, metadata: array<string, string>} $state */
    private function checkoutResponse(Subscription $subscription, array $state, int $status): JsonResponse
    {
        return response()->json([
            'message' => 'Souscription enregistrée. Finalisez maintenant le paiement sécurisé.',
            'subscription' => $subscription,
            'payment' => [
                'provider' => 'stripe',
                'status' => 'pending',
                'redirect_required' => true,
                'checkout_url' => $state['url'],
                'expires_at' => $state['expires_at'],
            ],
        ], $status);
    }

    /** @param array{id: string, url: string, status: string, payment_status: string, amount_total: int, currency: string, expires_at: int|null, payment_intent_id: string|null, metadata: array<string, string>} $state */
    private function synchronizeStripeState(Subscription $subscription, array $state): void
    {
        $this->assertStripeCheckoutMatches($subscription, $state);

        if ($state['payment_status'] === 'paid' && $subscription->statut !== 'Succès') {
            $subscription->update([
                'statut' => 'Succès',
                'stripe_payment_intent_id' => $state['payment_intent_id'],
                'payment_currency' => 'XAF',
                'payment_confirmed_at' => now(),
            ]);
        }
    }

    /** @param array{id: string, url: string, status: string, payment_status: string, amount_total: int, currency: string, expires_at: int|null, payment_intent_id: string|null, metadata: array<string, string>} $state */
    private function assertStripeCheckoutMatches(Subscription $subscription, array $state): void
    {
        $matches = $state['id'] !== ''
            && ($subscription->stripe_checkout_session_id === null
                || hash_equals($subscription->stripe_checkout_session_id, $state['id']))
            && ($state['metadata']['subscription_id'] ?? '') === (string) $subscription->id
            && isset($state['metadata']['reference_transaction'])
            && hash_equals($subscription->reference_transaction, $state['metadata']['reference_transaction'])
            && $state['currency'] === 'xaf'
            && $state['amount_total'] === (int) round((float) $subscription->montant_total);

        if (! $matches) {
            Log::warning('Stripe Checkout session mismatch', ['subscription_id' => $subscription->id]);
            abort(409, 'La session de paiement ne correspond pas à cette souscription.');
        }
    }

    private function isAllowedCheckoutUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, config('services.stripe.checkout_allowed_hosts', []), true);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->subscriptions()->with(['product', 'paymentProofs'])->latest()->paginate(20)
        );
    }

    private function s3pResponse(Subscription $subscription, int $status = 200): JsonResponse
    {
        if ($subscription->valuation_status === 'staging_only') {
            return response()->json(['message' => 'Paiement de test Maviance réussi. Aucune part réelle n’est créditée.',
                'subscription' => $subscription, 'payment' => ['provider' => 's3p', 'status' => 'success', 'mode' => 'staging', 'redirect_required' => false]], $status);
        }
        return response()->json([
            'message' => match ($subscription->mobile_state) {
                'success' => $subscription->statut === 'Succès' ? 'Paiement confirmé.' : ($subscription->valuation_status === 'awaiting_payment_date'
                    ? 'Paiement confirmé par le prestataire. La date de réception doit encore être rapprochée avant attribution des parts.'
                    : 'Fonds reçus. Attribution des parts en attente de la VL à la date de réception.'),
                'quote_failed' => 'Préparation du paiement impossible. Vous pouvez réessayer.',
                'verification_required' => 'Résultat du paiement à vérifier. Ne relancez pas le débit.',
                'errored' => \App\Services\Payments\S3pPaymentError::message($subscription->s3p_error_code),
                'reversed' => 'Paiement annulé par le prestataire. Contactez le support.',
                default => 'Demande enregistrée. Consultez votre téléphone puis vérifiez le statut.',
            },
            'subscription' => $subscription,
            'payment' => ['provider' => 's3p', 'status' => $subscription->mobile_state, 'redirect_required' => false,
                'error_code' => $subscription->s3p_error_code, 'receipt_number' => $subscription->s3p_receipt_number],
        ], $status);
    }
}
