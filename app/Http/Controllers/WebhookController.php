<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

class WebhookController extends Controller
{
    public function handleStripe(Request $request): JsonResponse
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $signature = $request->header('Stripe-Signature');
        if ($secret === '' || ! is_string($signature)) {
            return response()->json(['message' => 'Webhook non configuré.'], 503);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), $signature, $secret);
        } catch (UnexpectedValueException) {
            return response()->json(['message' => 'Payload invalide.'], 400);
        } catch (SignatureVerificationException) {
            return response()->json(['message' => 'Signature invalide.'], 403);
        }

        $processed = match ($event->type) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $this->handleStripeCheckoutSuccess(
                $request,
                $event->data->object,
            ),
            'checkout.session.async_payment_failed', 'checkout.session.expired' => $this->handleStripeCheckoutFailure(
                $request,
                $event->data->object,
            ),
            'payment_intent.succeeded' => $this->handleStripePaymentIntentSuccess(
                $request,
                $event->data->object,
            ),
            default => null,
        };

        if ($processed === null) {
            return response()->json(['status' => 'ignored']);
        }

        return response()->json(['status' => $processed ? 'received' : 'rejected'], $processed ? 200 : 409);
    }

    private function handleStripeCheckoutSuccess(Request $request, object $session): bool
    {
        if ((string) ($session->payment_status ?? '') !== 'paid') {
            return true;
        }

        return DB::transaction(function () use ($request, $session) {
            $subscriptionId = (int) ($session->metadata->subscription_id ?? 0);
            $subscription = Subscription::query()->lockForUpdate()->find($subscriptionId);
            if (! $subscription) {
                return false;
            }

            $paymentIntent = $session->payment_intent ?? null;
            $paymentIntentId = is_string($paymentIntent)
                ? $paymentIntent
                : (is_object($paymentIntent) && isset($paymentIntent->id) ? (string) $paymentIntent->id : null);
            $matches = $subscription->moyen_paiement === 'card'
                && $subscription->stripe_checkout_session_id
                && hash_equals($subscription->stripe_checkout_session_id, (string) ($session->id ?? ''))
                && hash_equals($subscription->reference_transaction, (string) ($session->metadata->reference_transaction ?? ''))
                && (string) ($session->client_reference_id ?? '') === (string) $subscription->id
                && strtolower((string) ($session->currency ?? '')) === 'xaf'
                && (int) ($session->amount_total ?? -1) === (int) round((float) $subscription->montant_total);

            if (! $matches) {
                Log::warning('Stripe Checkout payment mismatch', ['subscription_id' => $subscription->id]);

                return false;
            }

            if ($subscription->statut !== 'Succès') {
                $subscription->update([
                    'statut' => 'Succès',
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'payment_currency' => 'XAF',
                    'payment_confirmed_at' => now(),
                    'provider_payload_hash' => hash('sha256', $request->getContent()),
                ]);
            }

            return true;
        });
    }

    private function handleStripeCheckoutFailure(Request $request, object $session): bool
    {
        return DB::transaction(function () use ($request, $session) {
            $subscriptionId = (int) ($session->metadata->subscription_id ?? 0);
            $subscription = Subscription::query()->lockForUpdate()->find($subscriptionId);
            if (! $subscription) {
                return false;
            }

            $matches = $subscription->moyen_paiement === 'card'
                && $subscription->stripe_checkout_session_id
                && hash_equals($subscription->stripe_checkout_session_id, (string) ($session->id ?? ''))
                && hash_equals($subscription->reference_transaction, (string) ($session->metadata->reference_transaction ?? ''));

            if (! $matches) {
                return false;
            }

            if ($subscription->statut !== 'Succès') {
                $subscription->update([
                    'statut' => 'Échec',
                    'provider_payload_hash' => hash('sha256', $request->getContent()),
                ]);
            }

            return true;
        });
    }

    private function handleStripePaymentIntentSuccess(Request $request, object $intent): bool
    {
        return DB::transaction(function () use ($request, $intent) {
            $subscriptionId = (int) ($intent->metadata->subscription_id ?? 0);
            $reference = (string) ($intent->metadata->reference_transaction ?? '');
            $subscription = Subscription::query()->lockForUpdate()->find($subscriptionId);
            if (! $subscription) {
                return false;
            }

            $amount = (int) ($intent->amount_received ?: $intent->amount);
            $matches = $subscription->moyen_paiement === 'card'
                && $reference !== ''
                && hash_equals($subscription->reference_transaction, $reference)
                && strtolower((string) $intent->currency) === 'xaf'
                && $amount === (int) round((float) $subscription->montant_total)
                && (! $subscription->stripe_payment_intent_id
                    || hash_equals($subscription->stripe_payment_intent_id, (string) $intent->id));

            if (! $matches) {
                Log::warning('Stripe payment mismatch', ['subscription_id' => $subscription->id]);

                return false;
            }

            if ($subscription->statut !== 'Succès') {
                $subscription->update([
                    'statut' => 'Succès',
                    'stripe_payment_intent_id' => (string) $intent->id,
                    'payment_currency' => 'XAF',
                    'payment_confirmed_at' => now(),
                    'provider_payload_hash' => hash('sha256', $request->getContent()),
                ]);
            }

            return true;
        });
    }

    public function handleMaviance(Request $request): JsonResponse
    {
        $privateKey = (string) config('services.maviance.private_key');
        $publicKey = (string) config('services.maviance.public_key');
        if (! config('services.maviance.enabled') || $privateKey === '' || $publicKey === '') {
            return response()->json(['message' => 'Webhook non configuré.'], 503);
        }

        $validated = $request->validate([
            'application' => ['required', 'string'],
            'app_transaction_ref' => ['required', 'string', 'max:100'],
            'transaction_ref' => ['required', 'string', 'max:120'],
            'transaction_type' => ['required', 'in:PAYIN'],
            'transaction_amount' => ['required', 'numeric', 'min:0'],
            'transaction_currency' => ['required', 'in:XAF'],
            'transaction_operator' => ['required', 'in:MCP,CM_MOMO,CM_OM,CARD'],
            'transaction_status' => ['required', 'in:SUCCESS,CANCELED,FAILED'],
            'signature' => ['required', 'string', 'size:32'],
        ]);

        $signedValue = $validated['transaction_ref']
            .$validated['transaction_type']
            .$this->canonicalAmount($validated['transaction_amount'])
            .$validated['transaction_currency']
            .$validated['transaction_operator']
            .$privateKey;

        if (! hash_equals(md5($signedValue), strtolower($validated['signature']))
            || ! hash_equals($publicKey, $validated['application'])) {
            Log::warning('Rejected Maviance callback with invalid signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Signature invalide.'], 403);
        }

        try {
            $processed = DB::transaction(function () use ($request, $validated) {
                $subscription = Subscription::query()
                    ->where('reference_transaction', $validated['app_transaction_ref'])
                    ->lockForUpdate()
                    ->first();

                if (! $subscription) {
                    return false;
                }

                $matches = ! $subscription->mobile_provider
                    && in_array($subscription->moyen_paiement, ['mobile_money', 'maviance', 'orange_money', 'mtn_momo'], true)
                    && abs((float) $validated['transaction_amount'] - (float) $subscription->montant_total) < 0.01
                    && $validated['transaction_currency'] === 'XAF'
                    && (! $subscription->maviance_transaction_ref
                        || hash_equals($subscription->maviance_transaction_ref, $validated['transaction_ref']));

                if (! $matches) {
                    Log::warning('Maviance payment mismatch', ['subscription_id' => $subscription->id]);

                    return false;
                }

                $newStatus = match ($validated['transaction_status']) {
                    'SUCCESS' => 'Succès',
                    'FAILED', 'CANCELED' => 'Échec',
                };

                if ($subscription->statut !== 'Succès') {
                    $subscription->update([
                        'statut' => $newStatus,
                        'maviance_transaction_ref' => $validated['transaction_ref'],
                        'payment_currency' => 'XAF',
                        'payment_confirmed_at' => $newStatus === 'Succès' ? now() : null,
                        'provider_payload_hash' => hash('sha256', $request->getContent()),
                    ]);
                }

                return true;
            });

            return response()->json(['status' => $processed ? 'received' : 'rejected'], $processed ? 200 : 409);
        } catch (Throwable $exception) {
            Log::error('Maviance webhook processing failed', ['exception' => $exception::class]);

            return response()->json(['message' => 'Traitement impossible.'], 500);
        }
    }

    private function canonicalAmount(mixed $amount): string
    {
        $formatted = number_format((float) $amount, 8, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
