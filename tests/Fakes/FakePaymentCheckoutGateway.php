<?php

namespace Tests\Fakes;

use App\Models\Subscription;
use App\Services\Payments\PaymentCheckoutGateway;

class FakePaymentCheckoutGateway implements PaymentCheckoutGateway
{
    /** @var array<string, array<string, mixed>> */
    public array $sessions = [];

    public function createSession(Subscription $subscription, int $attempt): array
    {
        $id = "cs_test_subscription_{$subscription->id}_{$attempt}";
        $state = [
            'id' => $id,
            'url' => "https://checkout.stripe.com/c/pay/{$id}",
            'status' => 'open',
            'payment_status' => 'unpaid',
            'amount_total' => (int) round((float) $subscription->montant_total),
            'currency' => 'xaf',
            'expires_at' => now()->addMinutes(30)->timestamp,
            'payment_intent_id' => null,
            'metadata' => [
                'subscription_id' => (string) $subscription->id,
                'reference_transaction' => (string) $subscription->reference_transaction,
            ],
        ];
        $this->sessions[$id] = $state;

        return $state;
    }

    public function retrieveSession(string $sessionId): array
    {
        return $this->sessions[$sessionId];
    }

    public function markPaid(string $sessionId, string $paymentIntentId = 'pi_test_paid'): void
    {
        $this->sessions[$sessionId]['status'] = 'complete';
        $this->sessions[$sessionId]['payment_status'] = 'paid';
        $this->sessions[$sessionId]['payment_intent_id'] = $paymentIntentId;
        $this->sessions[$sessionId]['url'] = '';
    }
}
