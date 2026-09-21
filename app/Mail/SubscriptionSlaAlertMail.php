<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class SubscriptionSlaAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Collection $overdueSubscriptions,
        public int $slaHours
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->overdueSubscriptions->count();

        return new Envelope(
            subject: "⚠ ALERTE SLA PEK — {$count} souscription(s) en attente de contrôle interne (> {$this->slaHours}h)",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription_sla_alert',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
