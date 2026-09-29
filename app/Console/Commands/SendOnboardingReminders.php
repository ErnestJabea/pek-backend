<?php

namespace App\Console\Commands;

use App\Mail\OnboardingReminderMail;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOnboardingReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onboarding:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoie une alerte par email et notification push tous les 2 jours aux clients ayant souscrit 1 fois sans onboarding validé.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $twoDaysAgo = Carbon::now()->subDays(2);

        $users = User::query()
            ->whereHas('subscriptions', function ($query) {
                $query->whereNotIn('statut', ['Annulée', 'Rejetée']);
            })
            ->where(function ($q) {
                $q->whereDoesntHave('onboardingSession')
                  ->orWhereHas('onboardingSession', function ($sub) {
                      $sub->where('status', '!=', 'validated');
                  });
            })
            ->where(function ($q) use ($twoDaysAgo) {
                $q->whereNull('last_onboarding_reminder_at')
                  ->orWhere('last_onboarding_reminder_at', '<=', $twoDaysAgo);
            })
            ->get();

        $count = 0;
        foreach ($users as $user) {
            try {
                // 1. Send Email
                Mail::to($user->email)->send(new OnboardingReminderMail($user));

                // 2. Create Notification (Push / In-App)
                Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Rappel : Finalisez votre onboarding KYC',
                    'body' => 'Vous avez effectué votre 1ère souscription. Veuillez remplir et soumettre votre dossier d\'onboarding afin de pouvoir effectuer de nouvelles souscriptions.',
                    'type' => 'warning',
                ]);

                // 3. Update timestamp
                $user->last_onboarding_reminder_at = Carbon::now();
                $user->save();

                $count++;
            } catch (Throwable $e) {
                Log::error("Erreur lors de l'envoi du rappel onboarding à l'utilisateur #{$user->id}: " . $e->getMessage());
            }
        }

        $this->info("Rappels d'onboarding envoyés à {$count} client(s).");
        return Command::SUCCESS;
    }
}
