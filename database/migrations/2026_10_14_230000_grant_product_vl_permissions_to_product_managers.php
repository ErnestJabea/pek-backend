<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Déclaration des permissions de gestion des VLs
        $vlPermissions = [
            'view_any_product_vl',
            'view_product_vl',
            'create_product_vl',
            'update_product_vl',
            'delete_product_vl',
        ];

        foreach ($vlPermissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        // 2. Attribution automatique à tous les rôles autorisés à modifier/gérer les produits
        $rolesWithProductUpdate = Role::whereHas('permissions', function ($q) {
            $q->whereIn('name', ['update_product', 'create_product']);
        })->get();

        foreach ($rolesWithProductUpdate as $role) {
            $role->givePermissionTo(['view_any_product_vl', 'view_product_vl', 'create_product_vl', 'update_product_vl']);
        }

        // 3. Attribution explicite au rôle 'test' s'il existe
        $testRole = Role::where('name', 'test')->first();
        if ($testRole) {
            $testRole->givePermissionTo(['view_any_product_vl', 'view_product_vl', 'create_product_vl', 'update_product_vl']);
        }

        // 4. Invalidation du cache de permissions Spatie
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Pas de rollback nécessaire
    }
};
