<?php

namespace App\Services\Payments;

use App\Models\Subscription;

interface LocalPaymentGateway
{
    /**
     * @return array{id: string, url: string, status: string, amount_total: int, currency: string, expires_at: int|null, reference: string, provider_name: string|null}
     */
    public function createOrder(Subscription $subscription, int $attempt): array;

    /**
     * @return array{id: string, url: string, status: string, amount_total: int, currency: string, expires_at: int|null, reference: string, provider_name: string|null}
     */
    public function retrieveOrder(string $transactionId): array;
}
