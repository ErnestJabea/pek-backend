<?php

namespace App\Jobs;

use App\Mail\SubscriptionMail;
use App\Models\Subscription;
use App\Services\SubscriptionBulletinService;
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
        $isConfirmedBank = in_array($subscription?->moyen_paiement, ['bank_transfer', 'virement'], true)
            && ($subscription?->payment_confirmed_at || $subscription?->funds_received_at);

        if (! $subscription || ($subscription->statut !== 'Succès' && ! $isConfirmedBank)) {
            return;
        }
        $mail = new SubscriptionMail($subscription);
        $bulletinService = app(SubscriptionBulletinService::class);
        $bulletinData = $bulletinService->getBulletinData($subscription);
        $pdf = Pdf::loadView('pdfs.bulletin', ['data' => $bulletinData])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        $mail->attachData($pdf->output(), "bulletin_souscription_{$subscription->reference_transaction}.pdf", [
            'mime' => 'application/pdf',
        ]);

        Mail::to($subscription->user->email)->send($mail);
    }
}
