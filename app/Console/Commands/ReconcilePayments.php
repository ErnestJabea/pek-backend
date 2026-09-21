<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Payments\BankPaymentService;
use App\Services\Payments\MobilePaymentService;
use App\Services\Payments\PaymentAudit;
use App\Services\Payments\S3pCallbackProcessor;
use Illuminate\Console\Command;

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
