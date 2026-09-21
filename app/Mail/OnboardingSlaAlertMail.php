<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class OnboardingSlaAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Collection $overdueSessions,
        public int $slaHours
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->overdueSessions->count();

        return new Envelope(
            subject: "⚠ ALERTE SLA PEK — {$count} dossier(s) onboarding en attente de validation (> {$this->slaHours}h)",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding_sla_alert',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
