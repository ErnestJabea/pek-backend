<?php

namespace App\Services\Payments;

use App\Models\Subscription;

interface PaymentCheckoutGateway
{
    /**
     * @return array{id: string, url: string, status: string, payment_status: string, amount_total: int, currency: string, expires_at: int|null, payment_intent_id: string|null, metadata: array<string, string>}
     */
    public function createSession(Subscription $subscription, int $attempt): array;

    /**
     * @return array{id: string, url: string, status: string, payment_status: string, amount_total: int, currency: string, expires_at: int|null, payment_intent_id: string|null, metadata: array<string, string>}
     */
    public function retrieveSession(string $sessionId): array;
}
