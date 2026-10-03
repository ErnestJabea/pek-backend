<?php

namespace App\Jobs;

use App\Mail\SubscriptionMail;
use App\Models\Subscription;
use App\Services\SubscriptionBulletinService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProcessSubscriptionReceipt
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Subscription $subscription) {}

    public function handle(): void
    {
        try {
            $subscription = $this->subscription->fresh(['user', 'product']);
            $isConfirmedBank = in_array($subscription?->moyen_paiement, ['bank_transfer', 'virement'], true)
                && ($subscription?->payment_confirmed_at || $subscription?->funds_received_at);

            if (! $subscription || ($subscription->statut !== 'Succès' && ! $isConfirmedBank)) {
                return;
            }

            if (! $subscription->user?->email) {
                Log::warning('Envoi du reçu ignoré : email utilisateur absent.', ['subscription_id' => $subscription->id]);
                return;
            }

            $mail = new SubscriptionMail($subscription);

            try {
                $bulletinService = app(SubscriptionBulletinService::class);
                $bulletinData = $bulletinService->getBulletinData($subscription);
                $pdf = Pdf::loadView('pdfs.bulletin', ['data' => $bulletinData])
                    ->setPaper('a4', 'portrait')
                    ->setOption('isRemoteEnabled', true)
                    ->setOption('isHtml5ParserEnabled', true);

                $mail->attachData($pdf->output(), "bulletin_souscription_{$subscription->reference_transaction}.pdf", [
                    'mime' => 'application/pdf',
                ]);
            } catch (Throwable $pdfError) {
                Log::error('Erreur lors de la génération du bulletin PDF de souscription : ' . $pdfError->getMessage(), [
                    'subscription_id' => $subscription->id,
                ]);
            }

            Mail::to($subscription->user->email)->send($mail);

            Log::info('Reçu et bulletin de souscription envoyés avec succès au client.', [
                'subscription_id' => $subscription->id,
                'email' => $subscription->user->email,
            ]);
        } catch (Throwable $e) {
            Log::error('Erreur lors de l’envoi du reçu de souscription : ' . $e->getMessage(), [
                'subscription_id' => $this->subscription->id ?? null,
                'exception' => $e::class,
            ]);
        }
    }
}