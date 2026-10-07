<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewClientRegistrationAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $client
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "✨ [PEK Notification] Nouvelle inscription client : {$this->client->first_name} {$this->client->last_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_new_client_registration',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
