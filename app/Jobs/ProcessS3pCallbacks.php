<?php

namespace App\Jobs;

use App\Services\Payments\MobilePaymentService;
use App\Services\Payments\S3pCallbackProcessor;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessS3pCallbacks
{
    use Dispatchable;

    public function __construct(public int $subscriptionId) {}

    public function handle(S3pCallbackProcessor $processor, MobilePaymentService $payments): void
    {
        // The durable inbox and scheduler recover interrupted post-response work.
        $processor->processDue($payments, $this->subscriptionId);
    }
}
