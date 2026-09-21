<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\DB;

class PaymentAudit
{
    public static function record(int $subscriptionId, string $type, array $details = [], ?int $actorId = null): void
    {
        DB::table('payment_events')->insert([
            'subscription_id' => $subscriptionId, 'actor_id' => $actorId,
            'type' => $type, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
