<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('onboarding_sessions')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException('Des utilisateurs possèdent plusieurs sessions KYC. Fusionnez-les avant de relancer la migration.');
        }

        Schema::table('onboarding_sessions', function (Blueprint $table) {
            $table->longText('encrypted_payload')->nullable();
            $table->longText('submitted_payload')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('identity_verified_at')->nullable();
            $table->unique('user_id', 'onboarding_sessions_user_unique');
        });

        DB::table('onboarding_sessions')
            ->whereNotNull('payload')
            ->orderBy('id')
            ->get(['id', 'payload'])
            ->each(function ($session) {
                $json = is_string($session->payload)
                    ? $session->payload
                    : json_encode($session->payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                DB::table('onboarding_sessions')->where('id', $session->id)->update([
                    'encrypted_payload' => Crypt::encryptString($json),
                    'payload' => null,
                ]);
            });

        Schema::create('onboarding_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('onboarding_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['onboarding_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_events');

        DB::table('onboarding_sessions')
            ->whereNotNull('encrypted_payload')
            ->orderBy('id')
            ->get(['id', 'encrypted_payload'])
            ->each(function ($session) {
                DB::table('onboarding_sessions')->where('id', $session->id)->update([
                    'payload' => Crypt::decryptString($session->encrypted_payload),
                ]);
            });

        Schema::table('onboarding_sessions', function (Blueprint $table) {
            $table->dropUnique('onboarding_sessions_user_unique');
            $table->dropColumn([
                'encrypted_payload',
                'submitted_payload',
                'revision',
                'rejection_reason',
                'submitted_at',
                'validated_at',
                'rejected_at',
                'identity_verified_at',
            ]);
        });
    }
};
