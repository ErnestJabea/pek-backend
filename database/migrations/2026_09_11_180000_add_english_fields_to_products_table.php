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
        Schema::table('products', function (Blueprint $table) {
            $table->string('libelle_en')->nullable()->after('libelle');
            $table->text('description_en')->nullable()->after('description');
            $table->string('depliant_en')->nullable()->after('depliant');
            $table->string('document_information_en')->nullable()->after('document_information');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'libelle_en',
                'description_en',
                'depliant_en',
                'document_information_en',
            ]);
        });
    }
};
