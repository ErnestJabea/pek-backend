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
        if (! Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique(); // card, orange_money, mtn_momo, bank_transfer
                $table->string('name');
                $table->string('name_en')->nullable();
                $table->text('description')->nullable();
                $table->text('description_en')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->integer('sort_order')->default(0);
                $table->unsignedBigInteger('min_amount')->nullable();
                $table->unsignedBigInteger('max_amount')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
