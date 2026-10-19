<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'civ')) {
                $table->string('civ', 10)->nullable()->after('last_name');
            }
        });

        // 1. Rétro-propager la civilité depuis les sessions d'onboarding existantes
        $sessions = DB::table('onboarding_sessions')
            ->whereNotNull('payload')
            ->select('user_id', 'payload')
            ->get();

        foreach ($sessions as $session) {
            if (empty($session->payload)) {
                continue;
            }

            $payload = is_array($session->payload) 
                ? $session->payload 
                : (json_decode($session->payload, true) ?: []);

            $civ = $payload['civ'] ?? null;
            if (!empty($civ) && in_array($civ, ['M.', 'Mme', 'Mr.', 'Mrs.', 'Ms.'])) {
                $standardCiv = in_array($civ, ['Mme', 'Mrs.', 'Ms.']) ? 'Mme' : 'M.';
                DB::table('users')
                    ->where('id', $session->user_id)
                    ->whereNull('civ')
                    ->update(['civ' => $standardCiv]);
            }
        }

        // 2. Mettre à jour spécifiquement Ernest ESSOME (ernestjabea@gmail.com)
        DB::table('users')
            ->where('email', 'ernestjabea@gmail.com')
            ->update(['civ' => 'M.']);

        // 3. Pour toute Personne Physique existante sans civilité, assigner M. par défaut
        DB::table('users')
            ->where('nature_client', 'Personne Physique')
            ->whereNull('civ')
            ->update(['civ' => 'M.']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'civ')) {
                $table->dropColumn('civ');
            }
        });
    }
};
