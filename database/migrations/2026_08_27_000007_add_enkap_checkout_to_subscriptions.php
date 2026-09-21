<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('enkap_merchant_reference', 36)->nullable()->unique()->after('maviance_transaction_ref');
            $table->text('provider_checkout_url')->nullable()->after('stripe_checkout_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['enkap_merchant_reference']);
            $table->dropColumn(['enkap_merchant_reference', 'provider_checkout_url']);
        });
    }
};
