<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Payments\BankPaymentService;
use App\Services\Payments\MobilePaymentService;
use App\Services\Payments\PaymentAudit;
use App\Services\Payments\S3pCallbackProcessor;
use App\Services\Payments\S3pGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Vérifier les paiements S3P et valoriser les virements à leur date de réception.';

    public function handle(BankPaymentService $bank, MobilePaymentService $mobile): int
    {
        app(S3pCallbackProcessor::class)->processDue($mobile);
        if (config('payments.s3p.enabled')) {
            Subscription::whereNotNull('s3p_pay_item')->where(function ($q) {
                $q->whereNull('mobile_checked_at')->orWhere('mobile_checked_at', '<', now()->subMinute());
            })->whereIn('mobile_state', ['submitted', 'pending', 'verification_required'])
                ->orderBy('mobile_checked_at')->limit(100)->get()->each(function ($sub) use ($mobile) {
                    try {
                        $mobile->refresh($sub);
                    } catch (\Throwable $e) {
                        PaymentAudit::record($sub->id, 'mobile_reconciliation_failed', ['exception' => $e::class]);
                    }
                });
        }
        // Recover dated confirmations previously held by the staging credit guard.
        Subscription::where('mobile_state', 'success')->whereIn('valuation_status', ['staging_only', 'awaiting_payment_date'])
            ->where(function ($q) {
                $q->whereNull('mobile_checked_at')->orWhere('mobile_checked_at', '<', now()->subMinute());
            })->orderBy('mobile_checked_at')->limit(100)->get()->each(function ($sub) use ($mobile) {
                if (! config('payments.s3p.enabled') || S3pGateway::mustKeepTestFundsSeparate($sub)) {
                    return;
                }
                $callback = DB::table('s3p_callback_inbox')->where('subscription_id', $sub->id)
                    ->where('provider_status', 'SUCCESS')->orderByDesc('id')->first();
                if (! $callback && ! config('payments.s3p.verified_timestamp_is_receipt') && ! S3pGateway::allowsStagingTimestamp($sub)) {
                    return;
                }
                try {
                    $mobile->refresh($sub, true, $callback);
                } catch (\Throwable $e) {
                    PaymentAudit::record($sub->id, 'mobile_date_reconciliation_failed', ['exception' => $e::class]);
                }
            });
        Subscription::where('valuation_status', 'awaiting_nav')->chunkById(100, function ($rows) use ($bank) {
            foreach ($rows as $sub) {
                try {
                    $bank->value($sub);
                } catch (\Throwable $e) {
                    PaymentAudit::record($sub->id, 'valuation_retry_required', ['exception' => $e::class]);
                }
            }
        });
        $this->info('Rapprochement terminé.');

        return self::SUCCESS;
    }
}
