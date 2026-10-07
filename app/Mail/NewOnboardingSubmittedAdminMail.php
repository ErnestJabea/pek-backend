<?php

namespace App\Mail;

use App\Models\OnboardingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewOnboardingSubmittedAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OnboardingSession $session
    ) {}

    public function envelope(): Envelope
    {
        $userName = $this->session->user
            ? "{$this->session->user->first_name} {$this->session->user->last_name}"
            : "Client #{$this->session->user_id}";

        $ref = $this->session->reference ?? 'KYC';

        return new Envelope(
            subject: "📋 [PEK Conformité] Nouveau dossier KYC soumis à valider : {$userName} ({$ref})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_new_onboarding_submitted',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
