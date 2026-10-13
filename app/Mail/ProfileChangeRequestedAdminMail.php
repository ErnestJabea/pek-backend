<?php

namespace App\Mail;

use App\Models\ProfileChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProfileChangeRequestedAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProfileChangeRequest $changeRequest
    ) {}

    public function envelope(): Envelope
    {
        $client = $this->changeRequest->user;
        $typeLabel = $this->changeRequest->request_type === 'bank_account' 
            ? 'Coordonnées Bancaires' 
            : 'Nature & Catégorie Client';

        return new Envelope(
            subject: "📋 [KORI Validation] Demande de modification ({$typeLabel}) : {$client->first_name} {$client->last_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_profile_change_requested',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
