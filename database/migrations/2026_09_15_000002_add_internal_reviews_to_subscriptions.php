<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('compliance_reviewed_at')->nullable()->after('payment_expires_at');
            $table->foreignId('compliance_reviewed_by_user_id')->nullable()->after('compliance_reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('accounting_reviewed_at')->nullable()->after('compliance_reviewed_by_user_id');
            $table->foreignId('accounting_reviewed_by_user_id')->nullable()->after('accounting_reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('internal_notes')->nullable()->after('accounting_reviewed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['compliance_reviewed_by_user_id']);
            $table->dropForeign(['accounting_reviewed_by_user_id']);
            $table->dropColumn([
                'compliance_reviewed_at',
                'compliance_reviewed_by_user_id',
                'accounting_reviewed_at',
                'accounting_reviewed_by_user_id',
                'internal_notes',
            ]);
        });
    }
};
