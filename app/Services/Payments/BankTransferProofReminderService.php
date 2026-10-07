<?php

namespace App\Services\Payments;

use App\Mail\BankTransferProofReminderMail;
use App\Models\Notification;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BankTransferProofReminderService
{
    /**
     * Send email and in-app reminder to the client for uploading their bank transfer proof.
     */
    public static function sendReminder(Subscription $subscription, ?int $actorId = null): bool
    {
        $subscription->loadMissing(['user', 'product']);

        if (!in_array($subscription->moyen_paiement, ['bank_transfer', 'virement'], true)) {
            return false;
        }

        if ($subscription->statut !== 'En attente' || $subscription->funds_received_at) {
            return false;
        }

        $user = $subscription->user;
        if (!$user || empty($user->email)) {
            return false;
        }

        try {
            // 1. Send Email Reminder
            Mail::to($user->email)->send(new BankTransferProofReminderMail($subscription));

            // 2. Create In-App Notification
            $formattedAmount = number_format((float) $subscription->montant_total, 0, ',', ' ');
            Notification::create([
                'user_id' => $user->id,
                'title' => 'Rappel : Justificatif de virement requis 📄',
                'body' => "Veuillez téléverser la preuve de votre virement pour la souscription {$subscription->reference_transaction} ({$formattedAmount} FCFA) afin de valider l'attribution de vos parts.",
                'type' => 'subscription',
            ]);

            // 3. Update reminder timestamp
            $subscription->forceFill(['last_proof_reminder_at' => now()])->saveQuietly();

            // 4. Audit trail
            PaymentAudit::record($subscription->id, 'transfer_proof_reminder_sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'reference' => $subscription->reference_transaction,
                'amount' => $subscription->montant_total,
                'actor_id' => $actorId,
            ], $actorId);

            return true;
        } catch (Throwable $e) {
            Log::error("Erreur lors de l'envoi du rappel de virement pour la souscription #{$subscription->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return false;
        }
    }
}
