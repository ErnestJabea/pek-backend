<?php

namespace App\Jobs;

use App\Mail\SubscriptionMail;
use App\Models\Subscription;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ProcessSubscriptionReceipt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public Subscription $subscription) {}

    public function handle(): void
    {
        $subscription = $this->subscription->fresh(['user', 'product']);
        if (!$subscription || $subscription->statut !== 'Succès') return;
        $mail = new SubscriptionMail($subscription);
        $pdf = Pdf::loadView('pdfs.receipt', ['subscription' => $subscription]);

        $mail->attachData($pdf->output(), "recu_{$subscription->reference_transaction}.pdf", [
            'mime' => 'application/pdf',
        ]);

        Mail::to($subscription->user->email)->send($mail);
    }
}
