<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('otp_codes')->delete();

        Schema::table('otp_codes', function (Blueprint $table) {
            $table->uuid('challenge_id')->nullable()->unique();
            $table->string('purpose', 20)->default('login');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->index(['email', 'purpose', 'expires_at'], 'otp_email_purpose_expiry_idx');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable();
            $table->string('stripe_payment_intent_id', 120)->nullable()->unique();
            $table->string('payment_currency', 3)->nullable();
            $table->char('provider_payload_hash', 64)->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->unique(['user_id', 'idempotency_key'], 'subscriptions_user_idempotency_unique');
        });

        Schema::table('product_vls', function (Blueprint $table) {
            $table->unique(['product_id', 'date_vl'], 'product_vls_product_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('product_vls', function (Blueprint $table) {
            $table->dropUnique('product_vls_product_date_unique');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique('subscriptions_user_idempotency_unique');
            $table->dropUnique(['stripe_payment_intent_id']);
            $table->dropColumn([
                'idempotency_key',
                'stripe_payment_intent_id',
                'payment_currency',
                'provider_payload_hash',
                'payment_confirmed_at',
            ]);
        });

        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropIndex('otp_email_purpose_expiry_idx');
            $table->dropUnique(['challenge_id']);
            $table->dropColumn(['challenge_id', 'purpose', 'attempts', 'consumed_at']);
        });
    }
};
