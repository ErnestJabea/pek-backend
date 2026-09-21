<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->json('s3p_context')->nullable();
            $table->timestamp('s3p_quote_expires_at')->nullable();
            $table->string('s3p_error_code', 32)->nullable();
            $table->string('s3p_receipt_number', 100)->nullable();
            $table->string('s3p_verification_code', 100)->nullable();
            $table->string('s3p_provider_timestamp', 64)->nullable();
            $table->char('s3p_response_hash', 64)->nullable();
        });
        Schema::create('s3p_callback_inbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->char('body_hash', 64)->unique();
            $table->string('delivery_id', 36);
            $table->string('ptn', 100);
            $table->string('provider_status', 16);
            $table->string('provider_timestamp', 64);
            $table->string('error_code', 32);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('received_at');
            $table->timestamp('next_attempt_at')->index();
            $table->timestamp('processed_at')->nullable()->index();
            $table->string('last_error', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s3p_callback_inbox');
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn([
            's3p_context', 's3p_quote_expires_at', 's3p_error_code', 's3p_receipt_number',
            's3p_verification_code', 's3p_provider_timestamp', 's3p_response_hash',
        ]));
    }
};
