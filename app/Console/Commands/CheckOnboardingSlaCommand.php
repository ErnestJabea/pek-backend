<?php

namespace App\Console\Commands;

use App\Mail\OnboardingSlaAlertMail;
use App\Models\OnboardingSession;
use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckOnboardingSlaCommand extends Command
{
    protected $signature = 'pek:check-onboarding-sla';

    protected $description = 'Vérifier les dépassements de SLA pour les validations d\'onboarding et alerter la conformité';

    public function handle(): int
    {
        $alertsEnabled = SystemSetting::get('sla_alerts_enabled', true);
        if (! $alertsEnabled) {
            $this->info('Alertes SLA désactivées dans les réglages système.');

            return self::SUCCESS;
        }

        $slaHours = SystemSetting::get('onboarding_sla_hours', 24);
        $complianceEmailsStr = SystemSetting::get('compliance_emails', 'conformite@koriassetmanagement.com');
        $emails = array_filter(array_map('trim', explode(',', $complianceEmailsStr)));

        if (empty($emails)) {
            $this->warn('Aucune adresse e-mail conformité n\'est configurée.');

            return self::FAILURE;
        }

        $threshold = now()->subHours($slaHours);

        $overdue = OnboardingSession::with('user')
            ->where('status', 'completed')
            ->where('updated_at', '<=', $threshold)
            ->get();

        if ($overdue->isEmpty()) {
            $this->info('Aucun dossier d\'onboarding en dépassement de SLA.');

            return self::SUCCESS;
        }

        $this->info("Détection de {$overdue->count()} dossier(s) en dépassement de SLA. Envoi d'alerte...");

        Mail::to($emails)->send(new OnboardingSlaAlertMail($overdue, $slaHours));

        $this->info('Alerte e-mail envoyée avec succès.');

        return self::SUCCESS;
    }
}
