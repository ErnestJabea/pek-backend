<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_events', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->cascadeOnDelete();
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->cascadeOnDelete();
        });

        if (Schema::hasTable('s3p_callback_inbox')) {
            Schema::table('s3p_callback_inbox', function (Blueprint $table) {
                $table->dropForeign(['subscription_id']);
                $table->foreign('subscription_id')
                    ->references('id')
                    ->on('subscriptions')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('payment_events', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->restrictOnDelete();
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->restrictOnDelete();
        });

        if (Schema::hasTable('s3p_callback_inbox')) {
            Schema::table('s3p_callback_inbox', function (Blueprint $table) {
                $table->dropForeign(['subscription_id']);
                $table->foreign('subscription_id')
                    ->references('id')
                    ->on('subscriptions')
                    ->restrictOnDelete();
            });
        }
    }
};
