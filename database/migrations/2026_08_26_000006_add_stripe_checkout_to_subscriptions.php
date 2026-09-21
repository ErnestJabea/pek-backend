<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_checkout_session_id', 160)->nullable()->unique();
            $table->unsignedSmallInteger('payment_attempt')->default(0);
            $table->timestamp('payment_initiated_at')->nullable();
            $table->timestamp('payment_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['stripe_checkout_session_id']);
            $table->dropColumn([
                'stripe_checkout_session_id',
                'payment_attempt',
                'payment_initiated_at',
                'payment_expires_at',
            ]);
        });
    }
};
