<?php

namespace App\Mail;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClientBirthdayMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $customMessage;

    public function __construct(public User $user, ?string $customMessage = null)
    {
        $this->customMessage = $customMessage 
            ?: SystemSetting::get('birthday_custom_message', 'Toute l\'équipe de KORI Asset Management vous adresse ses vœux les plus chaleureux de santé, de prospérité et de succès continu dans tous vos projets.');
    }

    public function build()
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $subject = "PEK - Joyeux Anniversaire {$this->user->first_name} !";

        return $this->subject($subject)->view('emails.client_birthday', [
            'user' => $this->user,
            'customMessage' => $this->customMessage,
            'frontendUrl' => $frontendUrl,
        ]);
    }
}