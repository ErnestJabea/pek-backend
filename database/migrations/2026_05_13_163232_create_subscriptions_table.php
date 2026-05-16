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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->decimal('nb_parts', 12, 4);
            $table->decimal('prix_unitaire', 12, 4);
            $table->decimal('montant_total', 12, 2);
            $table->string('moyen_paiement'); // OM, MOMO, Carte
            $table->string('statut')->default('En attente'); // En attente, Succès, Échec
            $table->string('reference_transaction')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
