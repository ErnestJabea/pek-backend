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

        $this->expirationDateFormatted = $expiryDate ? $expiryDate->format('d/m/Y') : 'date Ã©chue';
        $this->pieceType = $user->type_piece ?: 'PiÃ¨ce d\'identitÃ©';
    }

    public function build()
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $renewUrl = $frontendUrl . '/profile?renew_id=1';

        $isExpired = $this->daysRemaining <= 0;
        $subject = $isExpired
            ? "[KAM - PEK] Action requise : Votre piÃ¨ce d'identification est expirÃ©e"
            : "[KAM - PEK] Rappel : Votre piÃ¨ce d'identification expire dans {$this->daysRemaining} jours";

        $statusBadge = $isExpired
            ? "<span style='display:inline-block; background-color: #fee2e2; color: #991b1b; padding: 6px 14px; border-radius: 9999px; font-weight: bold; font-size: 13px;'>PiÃ¨ce expirÃ©e le {$this->expirationDateFormatted}</span>"
            : "<span style='display:inline-block; background-color: #fef3c7; color: #92400e; padding: 6px 14px; border-radius: 9999px; font-weight: bold; font-size: 13px;'>Expire le {$this->expirationDateFormatted} ({$this->daysRemaining} jours restants)</span>";

        $alertIntro = $isExpired
            ? "Nous constatons que votre piÃ¨ce d'identification (<strong>{$this->pieceType}</strong>) est <strong>arrivÃ©e Ã  expiration le {$this->expirationDateFormatted}</strong>."
            : "Nous vous informons que votre piÃ¨ce d'identification (<strong>{$this->pieceType}</strong>) <strong>arrive Ã  expiration dans {$this->daysRemaining} jours (le {$this->expirationDateFormatted})</strong>.";

        $html = "
            <div style='font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;'>
                <div style='text-align: center; margin-bottom: 24px;'>
                    <h2 style='color: #8E5E0A; margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -0.5px;'>KORI ASSET MANAGEMENT</h2>
                    <p style='color: #64748b; font-size: 12px; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px;'>Portefeuille Ã‰pargne Kori (PEK)</p>
                </div>

                <div style='margin-bottom: 20px; text-align: center;'>
                    {$statusBadge}
                </div>

                <h3 style='color: #1e293b; font-size: 17px; margin-top: 0;'>Bonjour {$this->user->first_name} {$this->user->last_name},</h3>

                <p style='color: #334155; font-size: 14px; line-height: 1.6;'>
                    {$alertIntro}
                </p>

                <p style='color: #334155; font-size: 14px; line-height: 1.6;'>
                    ConformÃ©ment aux exigences rÃ©glementaires de la COSUMAF relatives Ã  la tenue des comptes et Ã  la lutte contre le blanchiment des capitaux (LAB/FT), la validitÃ© de votre piÃ¨ce d'identification est indispensable pour la gestion de votre compte d'investissement.
                </p>

                <p style='color: #334155; font-size: 14px; line-height: 1.6;'>
                    Afin de garantir la continuitÃ© de vos opÃ©rations (souscriptions, rachats et Ã©dition de vos attestations fiscales), nous vous invitons Ã  renseigner votre nouveau document dÃ¨s maintenant sur votre espace client.
                </p>

                <div style='text-align: center; margin: 32px 0;'>
                    <a href='{$renewUrl}' 
                       style='background-color: #8E5E0A; color: #ffffff; padding: 14px 32px; text-decoration: none; font-weight: bold; border-radius: 12px; display: inline-block; font-size: 14px; box-shadow: 0 4px 12px rgba(142, 94, 10, 0.25);'>
                        Mettre Ã  jour ma piÃ¨ce d'identitÃ©
                    </a>
                </div>

                <div style='background-color: #f8fafc; border-left: 4px solid #8E5E0A; padding: 12px 16px; border-radius: 4px; margin: 24px 0;'>
                    <p style='color: #475569; font-size: 12px; line-height: 1.5; margin: 0;'>
                        <strong>PiÃ¨ces acceptÃ©es :</strong> Carte Nationale d'IdentitÃ© (CNI) en cours de validitÃ©, Passeport biomÃ©trique, ou Carte de RÃ©sident / SÃ©jour. PrÃ©parez une photo claire du recto et du verso de votre piÃ¨ce.
                    </p>
                </div>

                <p style='color: #64748b; font-size: 12px; line-height: 1.5;'>
                    Si vous avez dÃ©jÃ  transmis votre nouvelle piÃ¨ce rÃ©cemment, veuillez ne pas tenir compte de ce rappel. Notre Ã©quipe de conformitÃ© procÃ¨de actuellement Ã  son contrÃ´le.
                </p>

                <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;' />

                <div style='text-align: center;'>
                    <p style='color: #94a3b8; font-size: 11px; margin: 0;'>
                        KORI Asset Management â€” SociÃ©tÃ© de Gestion agrÃ©Ã©e COSUMAF
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