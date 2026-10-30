<?php

namespace Tests\Feature;

use App\Models\AdminDepartment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessControlModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Artisan::call('pek:sync-access-control');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_super_admin_has_unrestricted_access()
    {
        $superAdmin = User::factory()->create([
            'role' => 'admin',
        ]);
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->assignRole($role);

        $this->assertTrue($superAdmin->hasPermissionTo('view_any_subscription'));
        $this->assertTrue($superAdmin->hasPermissionTo('confirm_bank_payment'));
        $this->assertTrue($superAdmin->hasPermissionTo('create_client'));
    }

    public function test_user_can_view_rubrique_granted_by_role()
    {
        $dept = AdminDepartment::create([
            'name' => 'Test Commercial',
            'permissions' => ['create_subscription'],
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'test_commercial_role', 'guard_name' => 'web']);
        $permViewSub = Permission::firstOrCreate(['name' => 'view_any_subscription', 'guard_name' => 'web']);
        $role->givePermissionTo($permViewSub);

        $user = User::factory()->create([
            'admin_department_id' => $dept->id,
            'role' => 'collaborateur',
        ]);
        $user->assignRole($role);

        // A accès à la rubrique autorisée par son rôle
        $this->assertTrue($user->hasPermissionTo('view_any_subscription'));

        // N'a PAS accès à une rubrique non couverte par son rôle
        $this->assertFalse($user->hasPermissionTo('view_any_bank::detail'));
    }

    public function test_user_action_is_allowed_only_if_department_grants_it_and_role_has_rubrique()
    {
        // Département autorise le visa comptabilité
        $financeDept = AdminDepartment::create([
            'name' => 'Test Finance',
            'permissions' => ['confirm_bank_payment', 'create_subscription'],
            'is_active' => true,
        ]);

        // Rôle 1: a la rubrique Souscriptions
        $roleWithSub = Role::create(['name' => 'test_role_with_sub', 'guard_name' => 'web']);
        $permViewSub = Permission::firstOrCreate(['name' => 'view_any_subscription', 'guard_name' => 'web']);
        $permConfirm = Permission::firstOrCreate(['name' => 'confirm_bank_payment', 'guard_name' => 'web']);
        $permCreate = Permission::firstOrCreate(['name' => 'create_subscription', 'guard_name' => 'web']);
        
        $roleWithSub->givePermissionTo([$permViewSub, $permConfirm, $permCreate]);

        $userWithSub = User::factory()->create([
            'admin_department_id' => $financeDept->id,
        ]);
        $userWithSub->assignRole($roleWithSub);

        // Peut exécuter confirm_bank_payment car son département l'autorise ET son rôle voit les souscriptions
        $this->assertTrue($userWithSub->hasPermissionTo('confirm_bank_payment'));

        // Rôle 2: n'a PAS la rubrique Souscriptions (ex: rôle RH)
        $roleWithoutSub = Role::create(['name' => 'test_role_no_sub', 'guard_name' => 'web']);
        $permViewUser = Permission::firstOrCreate(['name' => 'view_any_user', 'guard_name' => 'web']);
        $roleWithoutSub->givePermissionTo($permViewUser);

        $userWithoutSub = User::factory()->create([
            'admin_department_id' => $financeDept->id,
        ]);
        $userWithoutSub->assignRole($roleWithoutSub);

        // Bloqué pour confirm_bank_payment même si dans le département Finance, car la rubrique souscription ne lui est pas visible !
        $this->assertFalse($userWithoutSub->hasPermissionTo('confirm_bank_payment'));
    }

    public function test_user_cannot_execute_action_not_in_department_even_if_in_role()
    {
        // Département Commercial sans visa bancaire
        $commDept = AdminDepartment::create([
            'name' => 'Test Commercial Only',
            'permissions' => ['create_subscription'],
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'test_role_full', 'guard_name' => 'web']);
        $permViewSub = Permission::firstOrCreate(['name' => 'view_any_subscription', 'guard_name' => 'web']);
        $permConfirm = Permission::firstOrCreate(['name' => 'confirm_bank_payment', 'guard_name' => 'web']);
        $role->givePermissionTo([$permViewSub, $permConfirm]);

        $user = User::factory()->create([
            'admin_department_id' => $commDept->id,
        ]);
        $user->assignRole($role);

        // confirm_bank_payment n'est PAS dans le département -> refusé !
        $this->assertFalse($user->hasPermissionTo('confirm_bank_payment'));

        // create_subscription est dans le département et la rubrique est visible -> autorisé !
        $this->assertTrue($user->hasPermissionTo('create_subscription'));
    }

    public function test_inactive_department_blocks_all_actions_and_rubriques_except_super_admin()
    {
        $inactiveDept = AdminDepartment::create([
            'name' => 'Test Inactif',
            'permissions' => ['create_subscription'],
            'is_active' => false,
        ]);

        $role = Role::create(['name' => 'test_inactive_role', 'guard_name' => 'web']);
        $permViewSub = Permission::firstOrCreate(['name' => 'view_any_subscription', 'guard_name' => 'web']);
        $role->givePermissionTo($permViewSub);

        $user = User::factory()->create([
            'admin_department_id' => $inactiveDept->id,
        ]);
        $user->assignRole($role);

        $this->assertFalse($user->hasPermissionTo('view_any_subscription'));
        $this->assertFalse($user->hasPermissionTo('create_subscription'));
    }

    public function test_custom_403_view_renders_friendly_error()
    {
        $response = $this->get('/errors/403');
        // Si route errors n'est pas exposée directement, on teste le rendu de la vue
        $view = $this->view('errors.403');
        $view->assertSee('Espace ou Action Restreint(e)');
        $view->assertSee('Revenir au tableau de bord');
    }
}
