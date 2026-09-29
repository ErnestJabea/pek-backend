<?php

namespace App\Mail;

use App\Models\OnboardingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardingValidatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $session;

    public $user;

    /** @var string[] Documents manquants dans le dossier */
    public array $missingDocs = [];

    public string $loginUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(OnboardingSession $session)
    {
        $this->session = $session;
        $this->user = $session->user;
        $this->missingDocs = $session->missingRequiredDocuments();

        $frontendUrl = config('app.frontend_url') ?: env('FRONTEND_URL', 'https://pek.koriassetmanagement.com');
        $this->loginUrl = rtrim($frontendUrl, '/').'/login';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'FCP KORI SÉRÉNITÉ - Votre Compte est Activé !',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding_validated',
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
