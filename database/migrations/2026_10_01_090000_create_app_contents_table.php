<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_contents')) {
            Schema::create('app_contents', function (Blueprint $table) {
                $table->id();
                $table->string('section')->index();
                $table->string('key')->unique();
                $table->string('title_fr')->nullable();
                $table->string('title_en')->nullable();
                $table->text('body_fr')->nullable();
                $table->text('body_en')->nullable();
                $table->string('image_path')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_contents');
    }
};