<?php

use App\Models\AdminDepartment;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            // Pages Custom Backoffice
            'page_ClientBirthdays',
            'page_IdDocumentExpirations',
            'page_KpiAnalytics',
            'page_ManageNotificationSettings',

            // Actions CRM & Conformité
            'wish_client_birthday',
            'remind_client_id',

            // Demandes de modification profil & RIB
            'view_any_profile_change_request',
            'update_profile_change_request',
            'delete_profile_change_request',

            // Contenus PWA
            'view_any_app_content',
            'create_app_content',
            'update_app_content',
            'delete_app_content',

            // Moyens de paiement
            'view_any_payment_method',
            'create_payment_method',
            'update_payment_method',
            'delete_payment_method',

            // Pays & Villes
            'view_any_country',
            'create_country',
            'update_country',
            'delete_country',
            'view_any_city',
            'create_city',
            'update_city',
            'delete_city',

            // Widgets
            'widget_CurrencyStats',
            'widget_BusinessDashboard',
            'widget_SubscriptionsCollectChartWidget',
            'widget_PaymentMethodsDistributionWidget',
            'widget_ProductVlEvolutionWidget',
            'widget_KycFunnelChartWidget',
            'widget_UserRegistrationsChartWidget',
            'widget_SubscriptionsStatusChartWidget',
        ];

        // 1. Découverte des guards enregistrés
        $guards = Role::pluck('guard_name')->unique()->filter()->values()->all();
        if (!in_array('web', $guards, true)) {
            $guards[] = 'web';
        }

        // 2. Création de toutes les permissions pour chaque guard
        foreach ($permissions as $permName) {
            foreach ($guards as $guard) {
                Permission::firstOrCreate(['name' => $permName, 'guard_name' => $guard]);
            }
        }

        // 3. Attribution complète au rôle super_admin
        $superAdminRoles = Role::where('name', 'super_admin')->get();
        foreach ($superAdminRoles as $role) {
            try {
                $role->givePermissionTo($permissions);
            } catch (\Throwable $e) {}
        }

        // 4. Mise à niveau du département "Conformité / KYC" avec les permissions de suivi des échéances et profils
        $complianceDept = AdminDepartment::where('name', 'Conformité / KYC')->first();
        if ($complianceDept) {
            $current = $complianceDept->permissions ?? [];
            $toAdd = [
                'page_IdDocumentExpirations',
                'remind_client_id',
                'view_any_profile_change_request',
                'update_profile_change_request',
            ];
            $merged = array_values(array_unique(array_merge($current, $toAdd)));
            $complianceDept->permissions = $merged;
            $complianceDept->save();
        }

        // 5. Invalidation du cache de permissions Spatie
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Pas de rollback destructif nécessaire
    }
};
