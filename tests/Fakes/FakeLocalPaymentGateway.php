<?php

namespace Tests\Fakes;

use App\Models\Subscription;
use App\Services\Payments\LocalPaymentGateway;

class FakeLocalPaymentGateway implements LocalPaymentGateway
{
    /** @var array<string, array<string, mixed>> */
    public array $orders = [];

    public function createOrder(Subscription $subscription, int $attempt): array
    {
        $id = "enkap_subscription_{$subscription->id}_{$attempt}";
        $reference = substr($subscription->reference_transaction.'-A'.$attempt, 0, 36);
        $state = [
            'id' => $id,
            'url' => "https://payment.enkap.cm/payment/ui/auth?stxid={$id}",
            'status' => 'CREATED',
            'amount_total' => (int) round((float) $subscription->montant_total),
            'currency' => 'XAF',
            'expires_at' => now()->addMinutes(30)->timestamp,
            'reference' => $reference,
            'provider_name' => null,
        ];
        $this->orders[$id] = $state;

        return $state;
    }

    public function retrieveOrder(string $transactionId): array
    {
        return $this->orders[$transactionId];
    }

    public function markPaid(string $transactionId, string $provider = 'mtn'): void
    {
        $this->orders[$transactionId]['status'] = 'CONFIRMED';
        $this->orders[$transactionId]['provider_name'] = $provider;
        $this->orders[$transactionId]['url'] = '';
    }
}
