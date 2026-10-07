<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Payments\BankTransferProofReminderService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendBankTransferProofReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:send-transfer-proof-reminders {--hours=24 : Heures minimales écoulées depuis la création de la souscription}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoie un rappel par email et notification in-app aux clients ayant initié un virement bancaire sans justificatif téléversé.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $threshold = Carbon::now()->subHours($hours > 0 ? $hours : 24);
        $twoDaysAgo = Carbon::now()->subDays(2);

        $subscriptions = Subscription::query()
            ->with(['user', 'product'])
            ->whereIn('moyen_paiement', ['bank_transfer', 'virement'])
            ->where('statut', 'En attente')
            ->whereNull('funds_received_at')
            ->where('created_at', '<=', $threshold)
            ->whereDoesntHave('paymentProofs')
            ->where(function ($q) use ($twoDaysAgo) {
                $q->whereNull('last_proof_reminder_at')
                  ->orWhere('last_proof_reminder_at', '<=', $twoDaysAgo);
            })
            ->get();

        $count = 0;
        foreach ($subscriptions as $subscription) {
            try {
                $sent = BankTransferProofReminderService::sendReminder($subscription);
                if ($sent) {
                    $count++;
                }
            } catch (Throwable $e) {
                Log::error("Erreur lors de la relance pour preuve de virement (Souscription #{$subscription->id}): " . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }

        $this->info("Rappels de justificatif de virement envoyés pour {$count} souscription(s).");

        return Command::SUCCESS;
    }
}
