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

        $statusBadge = $isExpired
            ? "<span style='display:inline-block; background-color: #fee2e2; color: #991b1b; padding: 6px 14px; border-radius: 9999px; font-weight: bold; font-size: 13px;'>Pièce expirée le {$this->expirationDateFormatted}</span>"
            : "<span style='display:inline-block; background-color: #fef3c7; color: #92400e; padding: 6px 14px; border-radius: 9999px; font-weight: bold; font-size: 13px;'>Expire le {$this->expirationDateFormatted} ({$this->daysRemaining} jours restants)</span>";

        $alertIntro = $isExpired
            ? "Nous constatons que votre pièce d'identification (<strong>{$this->pieceType}</strong>) est <strong>arrivée Ã  expiration le {$this->expirationDateFormatted}</strong>."
            : "Nous vous informons que votre pièce d'identification (<strong>{$this->pieceType}</strong>) <strong>arrive Ã  expiration dans {$this->daysRemaining} jours (le {$this->expirationDateFormatted})</strong>.";

        $html = "
            <div style='font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;'>
                <div style='text-align: center; margin-bottom: 24px;'>
                    <h2 style='color: #8E5E0A; margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -0.5px;'>KORI ASSET MANAGEMENT</h2>
                    <p style='color: #64748b; font-size: 12px; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px;'>Portefeuille Épargne Kori (PEK)</p>
                </div>

                <div style='margin-bottom: 20px; text-align: center;'>
                    {$statusBadge}
                </div>

                <h3 style='color: #1e293b; font-size: 17px; margin-top: 0;'>Bonjour {$this->user->first_name} {$this->user->last_name},</h3>

                <p style='color: #334155; font-size: 14px; line-height: 1.6;'>
                    {$alertIntro}
                </p>

                <p style='color: #334155; font-size: 14px; line-height: 1.6;'>
                    Conformément aux exigences réglementaires de la COSUMAF relatives Ã  la tenue des comptes et Ã  la lutte contre le blanchiment des capitaux (LAB/FT), la validité de votre pièce d'identification est indispensable pour la gestion de votre compte d'investissement.
                </p>

                <p style='color: #334155; font-size: 14px; line-height: 1.6;'>
                    Afin de garantir la continuité de vos opérations (souscriptions, rachats et édition de vos attestations fiscales), nous vous invitons Ã  renseigner votre nouveau document dès maintenant sur votre espace client.
                </p>

                <div style='text-align: center; margin: 32px 0;'>
                    <a href='{$renewUrl}' 
                       style='background-color: #8E5E0A; color: #ffffff; padding: 14px 32px; text-decoration: none; font-weight: bold; border-radius: 12px; display: inline-block; font-size: 14px; box-shadow: 0 4px 12px rgba(142, 94, 10, 0.25);'>
                        Mettre Ã  jour ma pièce d'identité
                    </a>
                </div>

                <div style='background-color: #f8fafc; border-left: 4px solid #8E5E0A; padding: 12px 16px; border-radius: 4px; margin: 24px 0;'>
                    <p style='color: #475569; font-size: 12px; line-height: 1.5; margin: 0;'>
                        <strong>Pièces acceptées :</strong> Carte Nationale d'Identité (CNI) en cours de validité, Passeport biométrique, ou Carte de Résident / Séjour. Préparez une photo claire du recto et du verso de votre pièce.
                    </p>
                </div>

                <p style='color: #64748b; font-size: 12px; line-height: 1.5;'>
                    Si vous avez déjÃ  transmis votre nouvelle pièce récemment, veuillez ne pas tenir compte de ce rappel. Notre équipe de conformité procède actuellement Ã  son contrôle.
                </p>

                <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;' />

                <div style='text-align: center;'>
                    <p style='color: #94a3b8; font-size: 11px; margin: 0;'>
                        KORI Asset Management — Société de Gestion agréée COSUMAF
                    </p>
                    <p style='color: #94a3b8; font-size: 11px; margin: 4px 0 0 0;'>
                        Cameroun â€¢ CEMAC â€¢ contact@kori-asset.com
                    </p>
                </div>
            </div>
        ";

        return $this->subject($subject)->html($html);
    }
}