<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Mail\SubscriptionMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

class ProcessSubscriptionReceipt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    public function handle(): void
    {
        $user = $this->subscription->user;
        $mail = new SubscriptionMail($this->subscription);
        
        try {
            if (class_exists('Barryvdh\DomPDF\Facade\Pdf')) {
                $pdf = Pdf::loadView('pdfs.receipt', ['subscription' => $this->subscription]);
                $mail->attachData($pdf->output(), "recu_{$this->subscription->reference_transaction}.pdf", [
                    'mime' => 'application/pdf'
                ]);
            }
            
            Mail::to($user->email)->send($mail);
        } catch (\Exception $e) {
            \Log::error("Failed to process subscription receipt for {$this->subscription->id}: " . $e->getMessage());
        }
    }
}
