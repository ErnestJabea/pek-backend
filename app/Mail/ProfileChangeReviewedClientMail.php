<?php

namespace App\Mail;

use App\Models\ProfileChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProfileChangeReviewedClientMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProfileChangeRequest $changeRequest
    ) {}

    public function envelope(): Envelope
    {
        $statusLabel = $this->changeRequest->status === 'approved' ? 'Validée ✅' : 'Non validée ⚠️';
        $typeLabel = $this->changeRequest->request_type === 'bank_account' 
            ? 'coordonnées bancaires' 
            : 'catégorie client';

        return new Envelope(
            subject: "Mise à jour de vos {$typeLabel} : {$statusLabel} - PEK",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client_profile_change_reviewed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
