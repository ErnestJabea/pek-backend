<?php

namespace App\Console\Commands;

use App\Filament\Resources\AdminDepartmentResource;
use App\Models\AdminDepartment;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncAccessControlCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pek:sync-access-control {--force : Forcer la réinitialisation des permissions standard}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise le modèle de contrôle d\'accès PEK (Rôles = Rubriques, Départements = Actions) pour le déploiement en production.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('===============================================================');
        $this->info('   PEK - SYNCHRONISATION DU CONTRÔLE D\'ACCÈS (PRODUCTION READY)   ');
        $this->info('===============================================================');

        // 1. Réinitialiser le cache Spatie
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->line('<fg=cyan>✔ Cache de permissions Spatie réinitialisé.</>');

        // 2. Définir l'ensemble exhaustif des rubriques et actions
        $rubriquePermissions = [
            // Menus / Ressources
            'view_any_subscription', 'view_subscription',
            'view_any_onboarding::session', 'view_onboarding::session',
            'view_any_client', 'view_client',
            'view_any_product', 'view_product',
            'view_any_product_vl', 'view_product_vl',
            'view_any_bank::detail', 'view_bank::detail',
            'view_any_currency', 'view_currency',
            'view_any_app_content',
            'view_any_payment_method',
            'view_any_country',
            'view_any_city',
            'view_any_profile_change_request',
            'view_any_user', 'view_user',
            'view_any_role', 'view_role',
            'view_any_admin::department', 'view_admin::department',
            'view_any_admin::access::event', 'view_admin::access::event',
            'access_admin_panel',
            'view_payment_proof',
            // Pages & Widgets
            'page_ClientBirthdays',
            'page_IdDocumentExpirations',
            'page_KpiAnalytics',
            'page_ManageNotificationSettings',
            'widget_BusinessDashboard',
            'widget_CurrencyStats',
            'widget_KycFunnelChartWidget',
            'widget_PaymentMethodsDistributionWidget',
            'widget_ProductVlEvolutionWidget',
            'widget_SubscriptionsCollectChartWidget',
            'widget_SubscriptionsStatusChartWidget',
            'widget_UserRegistrationsChartWidget',
        ];

        // Récupérer toutes les actions définies dans AdminDepartmentResource
        $actionModules = AdminDepartmentResource::getPermissionsByModule();
        $actionPermissions = [];
        foreach ($actionModules as $mod) {
            foreach (array_keys($mod['permissions']) as $perm) {
                $actionPermissions[] = $perm;
            }
        }

        $allRequired = array_unique(array_merge($rubriquePermissions, $actionPermissions));
        $createdCount = 0;

        foreach ($allRequired as $permName) {
            $perm = Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
            if ($perm->wasRecentlyCreated) {
                $createdCount++;
            }
        }
        $this->line("<fg=green>✔ {$createdCount} nouvelle(s) permission(s) créée(s). Total synchronisé : " . count($allRequired) . "</>");

        // 3. Garantir l'intégrité du Super Admin
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions(Permission::where('guard_name', 'web')->get());
        $this->line('<fg=green>✔ Rôle super_admin synchronisé avec la totalité des droits.</>');

        // 4. Initialisation / Mise à jour des Départements Standard si existants ou si --force
        $defaultDepts = [
            'Finance & Trésorerie' => [
                'description' => 'Département en charge de la comptabilité, de la validation des virements bancaires et de l\'attribution des parts.',
                'permissions' => [
                    'confirm_bank_payment',
                    'review_payment_proof',
                    'create_product_vl',
                    'update_product_vl',
                    'create_bank::detail',
                    'update_bank::detail',
                    'create_currency',
                    'update_currency',
                ],
            ],
            'Conformité & Contrôle' => [
                'description' => 'Département responsable des contrôles LAB/FT, du visa de conformité et de l\'approbation des dossiers KYC.',
                'permissions' => [
                    'review_subscription_compliance',
                    'update_onboarding::session',
                    'update_profile_change_request',
                    'review_payment_proof',
                ],
            ],
            'Opérations & Commercial' => [
                'description' => 'Département chargé de l\'accueil des investisseurs, de la saisie des souscriptions et de la gestion CRM.',
                'permissions' => [
                    'create_subscription',
                    'update_subscription',
                    'create_onboarding::session',
                    'create_client',
                    'update_client',
                    'wish_client_birthday',
                    'remind_client_id',
                ],
            ],
            'Direction & Administration' => [
                'description' => 'Département de supervision générale et administration du système.',
                'permissions' => $actionPermissions,
            ],
        ];

        foreach ($defaultDepts as $deptName => $info) {
            $dept = AdminDepartment::firstOrNew(['name' => $deptName]);
            if (! $dept->exists || $this->option('force')) {
                $dept->description = $info['description'];
                $dept->permissions = array_values(array_unique($info['permissions']));
                $dept->is_active = true;
                $dept->save();
                $this->line("<fg=yellow>→ Département [{$deptName}] synchronisé avec ses actions métiers.</>");
            }
        }

        // 5. Vider le cache de permissions final
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->info('✔ Synchronisation terminée avec succès ! Le contrôle d\'accès est opérationnel.');
        return Command::SUCCESS;
    }
}
