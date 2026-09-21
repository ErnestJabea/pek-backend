<?php

namespace App\Mail;

use App\Models\OnboardingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardingMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $recipientFirstName;

    public string $reference;

    public string $riskLevel;

    /** @var string[] Documents manquants dans le dossier */
    public array $missingDocs;

    /**
     * Create a new message instance.
     */
    public function __construct(OnboardingSession $session, public string $type)
    {
        $this->recipientFirstName = (string) $session->user->first_name;
        $this->reference = $session->reference;
        $this->riskLevel = (string) $session->risk_level;

        // Calcule les documents manquants dans le payload
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
        if ($this->type === 'client') {
            return new Envelope(
                subject: 'FCP KORI SÉRÉNITÉ - Votre Profil Investisseur',
            );
        }

        // Never place customer identity data in an email subject.
        $riskString = $this->riskLevel === 'HIGH' ? '[RISQUE ÉLEVÉ]' : '[RISQUE NORMAL]';

        return new Envelope(
            subject: "Onboarding PEK - {$riskString} - Référence {$this->reference}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding_completed',
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
