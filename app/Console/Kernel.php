<?php

namespace App\Console;

use App\Models\OtpCode;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('payments:reconcile')->everyMinute()->withoutOverlapping();
        $schedule->command('payments:scan-proofs')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('sanctum:prune-expired --hours=24')->daily();
        $schedule->call(function () {
            OtpCode::where('expires_at', '<', now()->subDay())->delete();
        })->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
