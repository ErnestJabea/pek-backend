<?php

namespace App\Mail;

use App\Models\BankDetail;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BankTransferProofReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public Subscription $subscription;
    public array $bank;
    public string $uploadUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription->loadMissing(['user', 'product']);
        
        $bankData = $subscription->bank_snapshot;
        if (empty($bankData)) {
            $defaultBank = BankDetail::where('is_active', true)->first();
            $bankData = $defaultBank ? $defaultBank->only(['bank_name', 'beneficiary', 'iban', 'rib', 'swift', 'bank_instructions']) : [];
        }
        $this->bank = (array) $bankData;

        $baseUrl = rtrim((string) config('app.frontend_url', 'https://pek.koriassetmanagement.com'), '/');
        $this->uploadUrl = $baseUrl . '/subscriptions';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Rappel : Justificatif de virement bancaire attendu — PEK (' . $this->subscription->reference_transaction . ')',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.bank_transfer_proof_reminder',
            with: [
                'subscription' => $this->subscription,
                'user' => $this->subscription->user,
                'product' => $this->subscription->product,
                'bank' => $this->bank,
                'uploadUrl' => $this->uploadUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
