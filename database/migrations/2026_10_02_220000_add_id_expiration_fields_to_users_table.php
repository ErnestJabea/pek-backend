<?php

use App\Models\OnboardingSession;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'expiration_piece')) {
                $table->date('expiration_piece')->nullable()->after('num_piece');
            }
            if (! Schema::hasColumn('users', 'last_id_expiry_reminder_at')) {
                $table->timestamp('last_id_expiry_reminder_at')->nullable()->after('last_onboarding_reminder_at');
            }
        });

        // Backfill expiration_piece from existing onboarding sessions
        try {
            $sessions = OnboardingSession::query()->whereNotNull('payload')->orWhereNotNull('encrypted_payload')->get();
            foreach ($sessions as $session) {
                $payload = $session->payload ?? [];
                if (! empty($payload['expiration_piece']) && $session->user_id) {
                    DB::table('users')
                        ->where('id', $session->user_id)
                        ->whereNull('expiration_piece')
                        ->update(['expiration_piece' => $payload['expiration_piece']]);
                }
            }
        } catch (\Throwable $e) {
            // Log or silence during migration
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'expiration_piece')) {
                $table->dropColumn('expiration_piece');
            }
            if (Schema::hasColumn('users', 'last_id_expiry_reminder_at')) {
                $table->dropColumn('last_id_expiry_reminder_at');
            }
        });
    }
};