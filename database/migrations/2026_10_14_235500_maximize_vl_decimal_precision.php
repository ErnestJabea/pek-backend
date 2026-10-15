<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Étend la précision des colonnes de Valeur Liquidative (VL) à DECIMAL(28, 12)
     * pour conserver l'intégralité absolue des décimales saisies (jusqu'à 12 décimales)
     * sans aucun arrondi ou troncature automatique.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // 1. Table product_vls
        if (Schema::hasTable('product_vls') && Schema::hasColumn('product_vls', 'vl')) {
            DB::statement('ALTER TABLE product_vls MODIFY COLUMN vl DECIMAL(28, 12) NOT NULL');
        }

        // 2. Table products
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'vl')) {
            DB::statement('ALTER TABLE products MODIFY COLUMN vl DECIMAL(28, 12) NOT NULL');
        }

        // 3. Table subscriptions (prix_unitaire)
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'prix_unitaire')) {
            DB::statement('ALTER TABLE subscriptions MODIFY COLUMN prix_unitaire DECIMAL(28, 12) NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('product_vls') && Schema::hasColumn('product_vls', 'vl')) {
            DB::statement('ALTER TABLE product_vls MODIFY COLUMN vl DECIMAL(18, 6) NOT NULL');
        }
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'vl')) {
            DB::statement('ALTER TABLE products MODIFY COLUMN vl DECIMAL(18, 6) NOT NULL');
        }
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'prix_unitaire')) {
            DB::statement('ALTER TABLE subscriptions MODIFY COLUMN prix_unitaire DECIMAL(18, 6) NOT NULL');
        }
    }
};
