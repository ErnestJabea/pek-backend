<?php

namespace App\Console\Commands;

use App\Mail\IdDocumentExpiryReminderMail;
use App\Models\Notification;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendIdDocumentExpiryReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'id:send-expiry-reminders {--dry-run : Simuler l\'envoi des rappels sans notifier} {--user-id= : Cibler un utilisateur specifique} {--force : Ignorer le delai anti-spam de 7 jours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoie des rappels automatiques (email et notification in-app) aux clients dont la piece d\'identite expire bientot (J-30) ou est expiree.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $targetUserId = $this->option('user-id');

        $enabled = SystemSetting::get('id_expiry_reminders_enabled', true);
        if (! $enabled && ! $force && ! $targetUserId) {
            $this->info("Les rappels automatiques d'expiration de pieces d'identite sont desactives dans les reglages.");
            return Command::SUCCESS;
        }

        $noticeDays = (int) SystemSetting::get('id_expiry_notice_days', 30);
        $throttleDays = (int) SystemSetting::get('id_expiry_throttle_days', 7);

        $this->info("Debut de la verification des pieces d'identite arrivant a expiration (seuil : {} jours)...");

        $query = User::query()->whereNull('deleted_at');

        if ($targetUserId) {
            $query->where('id', $targetUserId);
        }

        $users = $query->with('onboardingSession')->get();
        $sevenDaysAgo = Carbon::now()->subDays($throttleDays);
        $thirtyDaysFromNow = Carbon::now()->addDays($noticeDays)->endOfDay();

        $processedCount = 0;
        $skippedCount = 0;

        foreach ($users as $user) {
            $expiryStr = $user->effective_expiration_piece;
            if (empty($expiryStr)) {
                continue;
            }

            // Sync expiration_piece to user column if not set
            if (empty($user->expiration_piece)) {
                $user->expiration_piece = $expiryStr;
                $user->save();
            }

            $expiryDate = Carbon::parse($expiryStr)->endOfDay();

            // Only notify if expiring within 30 days OR already expired
            if ($expiryDate->gt($thirtyDaysFromNow)) {
                continue;
            }

            $daysRemaining = (int) now()->diffInDays($expiryDate, false);

            // Anti-spam throttle: do not send if notified within last 7 days unless --force
            if (! $force && $user->last_id_expiry_reminder_at && Carbon::parse($user->last_id_expiry_reminder_at)->gt($sevenDaysAgo)) {
                $skippedCount++;
                continue;
            }

            if ($isDryRun) {
                $status = $daysRemaining <= 0 ? "EXPIREE (" . abs($daysRemaining) . "j)" : "EXPIRE DANS {$daysRemaining}j";
                $this->line("[DRY-RUN] Client #{$user->id} ({$user->email}) : piece {$user->type_piece} - {$status} (le {$expiryDate->format('d/m/Y')})");
                $processedCount++;
                continue;
            }

            try {
                // 1. Send Email
                Mail::to($user->email)->send(new IdDocumentExpiryReminderMail($user, $daysRemaining));

                // 2. Create in-app Notification
                $formattedDate = $expiryDate->format('d/m/Y');
                $title = $daysRemaining <= 0
                    ? 'Action requise : Votre piÃ¨ce d\'identitÃ© a expirÃ©'
                    : "Rappel : Votre piÃ¨ce d'identitÃ© expire dans {$daysRemaining} jours";

                $body = $daysRemaining <= 0
                    ? "Votre piÃ¨ce d'identification ({$user->type_piece}) est expirÃ©e depuis le {$formattedDate}. Veuillez mettre Ã  jour votre document dans votre profil pour maintenir la conformitÃ© de votre compte."
                    : "Votre piÃ¨ce d'identification ({$user->type_piece}) arrive Ã  expiration le {$formattedDate} ({$daysRemaining} jours restants). Pensez Ã  renouveler votre piÃ¨ce dÃ¨s maintenant.";

                Notification::create([
                    'user_id' => $user->id,
                    'title' => $title,
                    'body' => $body,
                    'type' => 'warning',
                ]);

                // 3. Update last reminder timestamp
                $user->last_id_expiry_reminder_at = Carbon::now();
                $user->save();

                $processedCount++;
                $this->info("Rappel envoye avec succes a {$user->email} (expire le {$formattedDate})");
            } catch (Throwable $e) {
                Log::error("Erreur rappel expiration piece #{$user->id} ({$user->email}) : " . $e->getMessage());
                $this->error("Erreur pour #{$user->id}: " . $e->getMessage());
            }
        }

        $this->info("Termine : {$processedCount} rappel(s) traite(s), {$skippedCount} ignore(s) car recents.");
        return Command::SUCCESS;
    }
}