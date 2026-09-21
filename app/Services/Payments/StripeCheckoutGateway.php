<?php

namespace App\Services\Payments;

use App\Models\Subscription;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

class StripeCheckoutGateway implements PaymentCheckoutGateway
{
    public function createSession(Subscription $subscription, int $attempt): array
    {
        $subscription->loadMissing(['user', 'product']);

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        if ($frontendUrl === '') {
            throw new RuntimeException('L’URL du frontend de paiement n’est pas configurée.');
        }

        if (app()->environment('production') && parse_url($frontendUrl, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('L’URL du frontend de paiement doit utiliser HTTPS en production.');
        }

        $metadata = [
            'subscription_id' => (string) $subscription->id,
            'reference_transaction' => (string) $subscription->reference_transaction,
        ];

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'client_reference_id' => (string) $subscription->id,
            'customer_email' => (string) $subscription->user->email,
            'locale' => 'fr',
            'success_url' => $frontendUrl.'/payment/return?subscription_id='.$subscription->id.'&checkout_session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $frontendUrl.'/payment/return?subscription_id='.$subscription->id.'&canceled=1',
            'expires_at' => now()->addMinutes((int) config('services.stripe.checkout_ttl_minutes', 30))->timestamp,
            'metadata' => $metadata,
            'payment_intent_data' => [
                'description' => 'Souscription PEK '.$subscription->reference_transaction,
                'metadata' => $metadata,
                'receipt_email' => (string) $subscription->user->email,
            ],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'xaf',
                    'unit_amount' => (int) round((float) $subscription->montant_total),
                    'product_data' => [
                        'name' => (string) $subscription->product->libelle,
                        'description' => 'Souscription au fonds PEK',
                    ],
                ],
                'quantity' => 1,
            ]],
        ], [
            'idempotency_key' => "pek-checkout-{$subscription->id}-attempt-{$attempt}",
        ]);

        return $this->normalize($session);
    }

    public function retrieveSession(string $sessionId): array
    {
        return $this->normalize($this->client()->checkout->sessions->retrieve($sessionId));
    }

    private function client(): StripeClient
    {
        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            throw new RuntimeException('Stripe n’est pas configuré.');
        }

        return new StripeClient($secret);
    }

    /**
     * @return array{id: string, url: string, status: string, payment_status: string, amount_total: int, currency: string, expires_at: int|null, payment_intent_id: string|null, metadata: array<string, string>}
     */
    private function normalize(Session $session): array
    {
        $metadata = $session->metadata && method_exists($session->metadata, 'toArray')
            ? $session->metadata->toArray()
            : [];
        $paymentIntent = $session->payment_intent;

        return [
            'id' => (string) $session->id,
            'url' => (string) ($session->url ?? ''),
            'status' => (string) ($session->status ?? ''),
            'payment_status' => (string) ($session->payment_status ?? ''),
            'amount_total' => (int) ($session->amount_total ?? 0),
            'currency' => strtolower((string) ($session->currency ?? '')),
            'expires_at' => isset($session->expires_at) ? (int) $session->expires_at : null,
            'payment_intent_id' => is_string($paymentIntent)
                ? $paymentIntent
                : (is_object($paymentIntent) && isset($paymentIntent->id) ? (string) $paymentIntent->id : null),
            'metadata' => array_map('strval', $metadata),
        ];
    }
}
