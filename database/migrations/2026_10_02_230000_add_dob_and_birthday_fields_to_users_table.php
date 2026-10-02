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
            if (! Schema::hasColumn('users', 'dob')) {
                $table->date('dob')->nullable()->after('city');
            }
            if (! Schema::hasColumn('users', 'last_birthday_wish_sent_at')) {
                $table->timestamp('last_birthday_wish_sent_at')->nullable()->after('last_id_expiry_reminder_at');
            }
        });

        // Backfill dob from existing onboarding sessions
        try {
            $sessions = OnboardingSession::query()->whereNotNull('payload')->orWhereNotNull('encrypted_payload')->get();
            foreach ($sessions as $session) {
                $payload = $session->payload ?? [];
                if (! empty($payload['dob']) && $session->user_id) {
                    DB::table('users')
                        ->where('id', $session->user_id)
                        ->whereNull('dob')
                        ->update(['dob' => $payload['dob']]);
                }
            }
        } catch (\Throwable $e) {
            // Silence if table or session not available during migration
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'dob')) {
                $table->dropColumn('dob');
            }
            if (Schema::hasColumn('users', 'last_birthday_wish_sent_at')) {
                $table->dropColumn('last_birthday_wish_sent_at');
            }
        });
    }
};