<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('investment_amount')->nullable();
            $table->unsignedBigInteger('subscription_fee')->nullable();
            $table->json('bank_snapshot')->nullable();
            $table->timestamp('funds_received_at')->nullable();
            $table->date('value_date')->nullable();
            $table->string('valuation_status', 30)->nullable();
            $table->string('bank_reference', 120)->nullable();
            $table->char('bank_transaction_key', 64)->nullable()->unique();
            $table->string('mobile_provider', 30)->nullable();
            $table->text('payment_phone')->nullable();
            $table->string('s3p_reference', 36)->nullable()->unique();
            $table->string('s3p_ptn', 100)->nullable()->unique();
            $table->string('s3p_pay_item', 255)->nullable();
            $table->string('s3p_quote_id', 100)->nullable();
            $table->string('mobile_state', 30)->nullable()->index();
            $table->timestamp('mobile_checked_at')->nullable();
            $table->timestamp('mobile_initiated_at')->nullable();
        });
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 80);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64)->index();
            $table->string('scan_status', 30)->default('quarantined');
            $table->string('review_status', 30)->default('received');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->date('declared_date');
            $table->unsignedBigInteger('declared_amount');
            $table->string('declared_reference', 120)->nullable();
            $table->timestamps();
        });
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('type', 60);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        foreach (['view_payment_proof', 'review_payment_proof', 'confirm_bank_payment', 'review_subscription_compliance'] as $permission) {
            \Spatie\Permission\Models\Permission::findOrCreate($permission, 'web');
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_proofs');
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['investment_amount', 'subscription_fee', 'bank_snapshot', 'funds_received_at', 'value_date', 'valuation_status', 'bank_reference', 'bank_transaction_key', 'mobile_provider', 'payment_phone', 's3p_reference', 's3p_ptn', 's3p_pay_item', 's3p_quote_id', 'mobile_state', 'mobile_checked_at', 'mobile_initiated_at']);
        });
    }
};
