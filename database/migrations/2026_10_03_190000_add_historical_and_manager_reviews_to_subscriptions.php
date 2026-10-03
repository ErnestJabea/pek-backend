<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'is_historical')) {
                $table->boolean('is_historical')->default(false)->after('statut')->index();
            }
            if (!Schema::hasColumn('subscriptions', 'manager_reviewed_at')) {
                $table->timestamp('manager_reviewed_at')->nullable()->after('accounting_reviewed_by_user_id');
            }
            if (!Schema::hasColumn('subscriptions', 'manager_reviewed_by_user_id')) {
                $table->foreignId('manager_reviewed_by_user_id')->nullable()->after('manager_reviewed_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'manager_reviewed_by_user_id')) {
                $table->dropForeign(['manager_reviewed_by_user_id']);
                $table->dropColumn('manager_reviewed_by_user_id');
            }
            if (Schema::hasColumn('subscriptions', 'manager_reviewed_at')) {
                $table->dropColumn('manager_reviewed_at');
            }
            if (Schema::hasColumn('subscriptions', 'is_historical')) {
                $table->dropColumn('is_historical');
            }
        });
    }
};
