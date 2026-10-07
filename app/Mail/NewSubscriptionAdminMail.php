<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewSubscriptionAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription
    ) {}

    public function envelope(): Envelope
    {
        $userName = $this->subscription->user
            ? "{$this->subscription->user->first_name} {$this->subscription->user->last_name}"
            : "Client #{$this->subscription->user_id}";

        $amountFormatted = number_format((float) $this->subscription->montant_total, 0, ',', ' ');

        return new Envelope(
            subject: "💳 [PEK Opérations] Nouvelle souscription reçue : {$amountFormatted} FCFA — {$userName} ({$this->subscription->reference_transaction})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_new_subscription',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
