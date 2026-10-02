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
            ?: SystemSetting::get('birthday_custom_message', 'Toute l\'Ã©quipe de KORI Asset Management vous adresse ses vÅ“ux les plus chaleureux de santÃ©, de prospÃ©ritÃ© et de succÃ¨s continu dans tous vos projets.');
    }

    public function build()
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $subject = "[KAM - PEK] Joyeux Anniversaire {$this->user->first_name} ! ðŸŽ‰";

        $html = "
            <div style='font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; padding: 28px; border: 1px solid #e2e8f0; border-radius: 20px; background-color: #ffffff;'>
                <div style='text-align: center; margin-bottom: 24px;'>
                    <h2 style='color: #8E5E0A; margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -0.5px;'>KORI ASSET MANAGEMENT</h2>
                    <p style='color: #64748b; font-size: 11px; margin-top: 4px; text-transform: uppercase; letter-spacing: 1.5px;'>Gestion d'Actifs & Ã‰pargne (PEK)</p>
                </div>

                <div style='text-align: center; margin-bottom: 24px;'>
                    <div style='display: inline-block; background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); border: 1px solid #FDE68A; color: #92400E; padding: 10px 24px; border-radius: 9999px; font-weight: 800; font-size: 14px;'>
                        ðŸŽ‚ Joyeux Anniversaire !
                    </div>
                </div>

                <h3 style='color: #1e293b; font-size: 18px; margin-top: 0; text-align: center;'>Cher(e) {$this->user->first_name} {$this->user->last_name},</h3>

                <p style='color: #334155; font-size: 14px; line-height: 1.7; text-align: center;'>
                    En ce jour si spÃ©cial, toute l'Ã©quipe de <strong>KORI Asset Management</strong> tient Ã  vous souhaiter un trÃ¨s heureux anniversaire !
                </p>

                <div style='background-color: #FAF6F0; border: 1px solid #E8B01033; border-radius: 16px; padding: 20px; margin: 24px 0; text-align: center;'>
                    <p style='color: #482010; font-size: 14px; line-height: 1.6; font-style: italic; margin: 0;'>
                        Â« {$this->customMessage} Â»
                    </p>
                </div>

                <p style='color: #334155; font-size: 14px; line-height: 1.7;'>
                    Nous profitons de cette occasion pour vous remercier chaleureusement de votre confiance au sein du <strong>Portefeuille Ã‰pargne Kori (PEK)</strong>. C'est un privilÃ¨ge de vous accompagner chaque jour dans la concrÃ©tisation de vos objectifs patrimoniaux et financiers.
                </p>

                <div style='text-align: center; margin: 32px 0;'>
                    <a href='{$frontendUrl}' 
                       style='background-color: #8E5E0A; color: #ffffff; padding: 14px 32px; text-decoration: none; font-weight: bold; border-radius: 12px; display: inline-block; font-size: 14px; box-shadow: 0 4px 14px rgba(142, 94, 10, 0.25);'>
                        AccÃ©der Ã  mon espace PEK
                    </a>
                </div>

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