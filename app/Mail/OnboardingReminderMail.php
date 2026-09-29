<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OnboardingReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function build()
    {
        return $this->subject('Rappel : Complétez votre onboarding pour continuer vos souscriptions')
            ->html("
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; rounded: 12px;'>
                    <h2 style='color: #8E5E0A;'>Bonjour {$this->user->first_name},</h2>
                    <p style='color: #334155; font-size: 15px; line-height: 1.6;'>
                        Félicitations pour votre première souscription sur notre plateforme KORI Asset Management !
                    </p>
                    <p style='color: #334155; font-size: 15px; line-height: 1.6;'>
                        Afin de pouvoir effectuer votre seconde souscription et profiter de tous nos services sans plafond de montant, nous vous invitons à finaliser et soumettre votre dossier d'onboarding (KYC).
                    </p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . config('app.frontend_url', 'http://localhost:5173') . "/onboarding' 
                           style='background-color: #8E5E0A; color: white; padding: 14px 28px; text-decoration: none; font-weight: bold; border-radius: 8px; display: inline-block;'>
                            Compléter mon Onboarding
                        </a>
                    </div>
                    <p style='color: #64748b; font-size: 13px;'>
                        Ce rappel vous est envoyé tous les 2 jours jusqu'à la validation de votre dossier d'onboarding par notre équipe de Conformité.
                    </p>
                    <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;' />
                    <p style='color: #94a3b8; font-size: 12px; text-align: center;'>
                        KORI Asset Management — Société de Gestion agréée COSUMAF
                    </p>
                </div>
            ");
    }
}
