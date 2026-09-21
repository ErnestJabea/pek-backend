<?php

namespace App\Mail;

use App\Models\OnboardingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardingRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $session;

    public $user;

    public $reason;

    /** @var string[] Documents manquants dans le dossier */
    public array $missingDocs;

    /**
     * Create a new message instance.
     */
    public function __construct(OnboardingSession $session, string $reason)
    {
        $this->session = $session;
        $this->user = $session->user;
        $this->reason = $reason;

        // Calcule les documents manquants
        $payload = $session->payload ?? [];
        $this->missingDocs = [];

        if (empty($payload['piece_recto']) && empty($payload['doc_piece_identite']) && ! $session->doc_piece_identite) {
            $this->missingDocs[] = "Pièce d'identité (CNI / Passeport)";
        }
        if (empty($payload['doc_justificatif_domicile']) && ! $session->doc_justificatif_domicile) {
            $this->missingDocs[] = 'Justificatif de domicile (< 3 mois)';
        }
        if (empty($payload['selfie_live']) && empty($payload['doc_photo']) && ! $session->doc_photo) {
            $this->missingDocs[] = "Photo d'identité récente";
        }
        if (empty($payload['doc_origine_fonds']) && ! $session->doc_origine_fonds) {
            $this->missingDocs[] = "Justificatif d'origine des fonds";
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'PEK - Informations Complémentaires Requises',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding_rejected',
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
