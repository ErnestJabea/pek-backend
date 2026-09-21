<?php

namespace App\Services\Payments;

use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class S3pCallbackProcessor
{
    public function processDue(MobilePaymentService $payments): int
    {
        if (!config('payments.s3p.enabled')) return 0;
        $processed = 0;
        $ids = DB::table('s3p_callback_inbox')->whereNull('processed_at')->where('next_attempt_at', '<=', now())->orderBy('id')->limit(100)->pluck('id');
        foreach ($ids as $id) {
            $callback = DB::transaction(function () use ($id) {
                $row = DB::table('s3p_callback_inbox')->where('id', $id)->lockForUpdate()->first();
                if (!$row || $row->processed_at || $row->next_attempt_at > now()->toDateTimeString()) return null;
                // A durable lease also recovers a worker crash without losing the callback.
                DB::table('s3p_callback_inbox')->where('id', $id)->update(['attempts' => $row->attempts + 1, 'next_attempt_at' => now()->addMinutes(2)]);
                return $row;
            });
            if (!$callback) continue;
            try {
                $sub = Subscription::findOrFail($callback->subscription_id);
                $payments->refresh($sub, true, $callback);
                DB::table('s3p_callback_inbox')->where('id', $id)->update(['processed_at' => now(), 'last_error' => null]);
                $processed++;
            } catch (\Throwable $e) {
                DB::table('s3p_callback_inbox')->where('id', $id)->update([
                    'next_attempt_at' => now()->addSeconds(min(3600, 60 * (2 ** min(6, $callback->attempts)))),
                    'last_error' => substr($e::class, 0, 100),
                ]);
                PaymentAudit::record($callback->subscription_id, 'mobile_callback_deferred', ['inbox_id' => $id, 'exception' => $e::class]);
            }
        }
        return $processed;
    }
}
