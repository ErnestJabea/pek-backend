<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionSlaAlertMail;
use App\Models\Subscription;
use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckSubscriptionSlaCommand extends Command
{
    protected $signature = 'pek:check-subscription-sla';

    protected $description = 'Vérifier les dépassements de SLA pour le contrôle interne des souscriptions';

    public function handle(): int
    {
        $alertsEnabled = SystemSetting::get('sla_alerts_enabled', true);
        if (! $alertsEnabled) {
            $this->info('Alertes SLA désactivées dans les réglages système.');

            return self::SUCCESS;
        }

        $slaHours = SystemSetting::get('subscription_sla_hours', 48);
        $complianceStr = SystemSetting::get('compliance_emails', 'conformite@koriassetmanagement.com');
        $accountingStr = SystemSetting::get('accounting_emails', 'comptabilite@koriassetmanagement.com');

        $allEmails = array_unique(array_filter(array_merge(
            array_map('trim', explode(',', $complianceStr)),
            array_map('trim', explode(',', $accountingStr))
        )));

        if (empty($allEmails)) {
            $this->warn('Aucune adresse e-mail configurée pour la conformité ou la comptabilité.');

            return self::FAILURE;
        }

        $threshold = now()->subHours($slaHours);

        $overdue = Subscription::with(['user', 'product'])
            ->where('statut', 'Succès')
            ->where(function ($query) {
                $query->whereNull('compliance_reviewed_at')
                    ->orWhereNull('accounting_reviewed_at');
            })
            ->where('created_at', '<=', $threshold)
            ->get();

        if ($overdue->isEmpty()) {
            $this->info('Aucune souscription en dépassement de SLA de contrôle interne.');

            return self::SUCCESS;
        }

        $this->info("Détection de {$overdue->count()} souscription(s) en dépassement SLA. Envoi d'alerte...");

        Mail::to($allEmails)->send(new SubscriptionSlaAlertMail($overdue, $slaHours));

        $this->info('Alerte e-mail envoyée avec succès.');

        return self::SUCCESS;
    }
}
