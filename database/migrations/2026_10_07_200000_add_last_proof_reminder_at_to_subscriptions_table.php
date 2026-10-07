<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'last_proof_reminder_at')) {
                $table->timestamp('last_proof_reminder_at')->nullable()->after('funds_received_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'last_proof_reminder_at')) {
                $table->dropColumn('last_proof_reminder_at');
            }
        });
    }
};
