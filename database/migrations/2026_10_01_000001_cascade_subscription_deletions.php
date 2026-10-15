<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_events')) {
            try {
                Schema::table('payment_events', function (Blueprint $table) {
                    $table->dropForeign(['subscription_id']);
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('payment_events', function (Blueprint $table) {
                    $table->foreign('subscription_id')
                        ->references('id')
                        ->on('subscriptions')
                        ->cascadeOnDelete();
                });
            } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('payment_proofs')) {
            try {
                Schema::table('payment_proofs', function (Blueprint $table) {
                    $table->dropForeign(['subscription_id']);
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('payment_proofs', function (Blueprint $table) {
                    $table->foreign('subscription_id')
                        ->references('id')
                        ->on('subscriptions')
                        ->cascadeOnDelete();
                });
            } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('s3p_callback_inbox')) {
            try {
                Schema::table('s3p_callback_inbox', function (Blueprint $table) {
                    $table->dropForeign(['subscription_id']);
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('s3p_callback_inbox', function (Blueprint $table) {
                    $table->foreign('subscription_id')
                        ->references('id')
                        ->on('subscriptions')
                        ->cascadeOnDelete();
                });
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_events')) {
            try {
                Schema::table('payment_events', function (Blueprint $table) {
                    $table->dropForeign(['subscription_id']);
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('payment_events', function (Blueprint $table) {
                    $table->foreign('subscription_id')
                        ->references('id')
                        ->on('subscriptions')
                        ->restrictOnDelete();
                });
            } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('payment_proofs')) {
            try {
                Schema::table('payment_proofs', function (Blueprint $table) {
                    $table->dropForeign(['subscription_id']);
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('payment_proofs', function (Blueprint $table) {
                    $table->foreign('subscription_id')
                        ->references('id')
                        ->on('subscriptions')
                        ->restrictOnDelete();
                });
            } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('s3p_callback_inbox')) {
            try {
                Schema::table('s3p_callback_inbox', function (Blueprint $table) {
                    $table->dropForeign(['subscription_id']);
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('s3p_callback_inbox', function (Blueprint $table) {
                    $table->foreign('subscription_id')
                        ->references('id')
                        ->on('subscriptions')
                        ->restrictOnDelete();
                });
            } catch (\Throwable $e) {}
        }
    }
};
