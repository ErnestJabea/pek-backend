<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IdDocumentExpiryReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public int $daysRemaining;
    public string $expirationDateFormatted;
    public string $pieceType;

    public function __construct(public User $user, ?int $daysRemaining = null)
    {
        $expiry = $user->effective_expiration_piece;
        $expiryDate = $expiry ? Carbon::parse($expiry)->endOfDay() : null;

        if ($daysRemaining !== null) {
            $this->daysRemaining = $daysRemaining;
        } elseif ($expiryDate) {
            $this->daysRemaining = (int) now()->diffInDays($expiryDate, false);
        } else {
            $this->daysRemaining = 0;
        }

        $this->expirationDateFormatted = $expiryDate ? $expiryDate->format('d/m/Y') : 'date échue';
        $this->pieceType = $user->type_piece ?: 'Pièce d\'identité';
    }

    public function build()
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $renewUrl = $frontendUrl . '/profile?renew_id=1';

        $isExpired = $this->daysRemaining <= 0;
        $subject = $isExpired
            ? "[KAM - PEK] Action requise : Votre pièce d'identification est expirée"
            : "[KAM - PEK] Rappel : Votre pièce d'identification expire dans {$this->daysRemaining} jours";

        return $this->subject($subject)
            ->view('emails.id_document_expiry_reminder', [
                'user' => $this->user,
                'daysRemaining' => $this->daysRemaining,
                'expirationDateFormatted' => $this->expirationDateFormatted,
                'pieceType' => $this->pieceType,
                'isExpired' => $isExpired,
                'renewUrl' => $renewUrl,
            ]);
    }
}