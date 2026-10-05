<?php

namespace App\Console\Commands;

use App\Mail\ClientBirthdayMail;
use App\Models\Notification;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendBirthdayWishes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'birthdays:send-wishes {--dry-run : Simuler l\'envoi des souhaits sans notifier} {--user-id= : Cibler un utilisateur specifique} {--force : Forcer l\'envoi meme si deja souhaite cette annee}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoie les souhaits d\'anniversaire automatiques (email et notification in-app) aux clients dont c\'est l\'anniversaire.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $targetUserId = $this->option('user-id');

        $enabled = SystemSetting::get('birthday_wishes_enabled', true);
        if (! $enabled && ! $force && ! $targetUserId) {
            $this->info("Les souhaits d'anniversaire automatiques sont desactives dans les reglages.");
            return Command::SUCCESS;
        }

        $noticeDays = (int) SystemSetting::get('birthday_notice_days', 0);
        $targetDate = Carbon::now()->addDays($noticeDays);
        $targetMonth = $targetDate->month;
        $targetDay = $targetDate->day;
        $currentYear = Carbon::now()->year;

        $this->info("Verification des anniversaires pour le {$targetDay}/{$targetMonth}...");

        $query = User::query()->whereNull('deleted_at');

        if ($targetUserId) {
            $query->where('id', $targetUserId);
        }

        $users = $query->with('onboardingSession')->get();
        $processedCount = 0;
        $skippedCount = 0;

        foreach ($users as $user) {
            $dobStr = $user->effective_dob;
            if (empty($dobStr)) {
                continue;
            }

            // Sync dob to user column if empty
            if (empty($user->dob)) {
                $user->dob = $dobStr;
                $user->save();
            }

            $dob = Carbon::parse($dobStr);

            // Check if month & day match
            if ($dob->month !== $targetMonth || $dob->day !== $targetDay) {
                // If targeting specific user with --force, bypass date check
                if (! ($targetUserId && $force)) {
                    continue;
                }
            }

            // Check if already sent this year
            if (! $force && $user->last_birthday_wish_sent_at) {
                $lastSent = Carbon::parse($user->last_birthday_wish_sent_at);
                if ($lastSent->year === $currentYear) {
                    $skippedCount++;
                    continue;
                }
            }

            $age = $user->age ?? $dob->diffInYears(Carbon::now());

            if ($isDryRun) {
                $this->line("[DRY-RUN] Anniversaire client #{$user->id} ({$user->first_name} {$user->last_name} - {$user->email}) : {$age} ans !");
                $processedCount++;
                continue;
            }

            try {
                // 1. Send Email
                Mail::to($user->email)->send(new ClientBirthdayMail($user));

                // 2. Create in-app Notification
                Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Joyeux Anniversaire ! 🎉',
                    'body' => "Toute l'équipe de KORI Asset Management vous souhaite un très heureux anniversaire !",
                    'type' => 'info',
                ]);

                // 3. Update timestamp
                $user->last_birthday_wish_sent_at = Carbon::now();
                $user->save();

                $processedCount++;
                $this->info("Souhait d'anniversaire envoye a {$user->email} ({$age} ans).");
            } catch (Throwable $e) {
                Log::error("Erreur souhait anniversaire client #{$user->id} ({$user->email}) : " . $e->getMessage());
                $this->error("Erreur pour #{$user->id}: " . $e->getMessage());
            }
        }

        $this->info("Termine : {$processedCount} souhait(s) traite(s), {$skippedCount} deja envoye(s) cette annee.");
        return Command::SUCCESS;
    }
}