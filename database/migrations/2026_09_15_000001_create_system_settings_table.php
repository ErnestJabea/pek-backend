<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->string('type')->default('string'); // string, integer, boolean, array
            $table->timestamps();
        });

        // Insert default settings
        DB::table('system_settings')->insert([
            [
                'key' => 'compliance_emails',
                'value' => 'conformite@koriassetmanagement.com',
                'description' => 'Adresses e-mail de l\'équipe Conformité (séparées par des virgules)',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'accounting_emails',
                'value' => 'comptabilite@koriassetmanagement.com',
                'description' => 'Adresses e-mail de l\'équipe Comptabilité (séparées par des virgules)',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'onboarding_sla_hours',
                'value' => '24',
                'description' => 'Délai d\'attente maximal (SLA) pour la validation d\'un onboarding (en heures)',
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'subscription_sla_hours',
                'value' => '48',
                'description' => 'Délai maximal (SLA) pour le contrôle interne des souscriptions (en heures)',
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'sla_alerts_enabled',
                'value' => '1',
                'description' => 'Activer les notifications automatiques d\'alerte de dépassement SLA',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
