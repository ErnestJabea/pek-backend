<?php
namespace Tests\Feature;

use App\Models\AdminDepartment;
use App\Models\User;
use App\Models\Subscription;
use App\Services\Payments\BankPaymentService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDepartmentsTest extends TestCase
{
    use RefreshDatabase;
    private function account(string $email = 'admin@example.test'): User
    {
        return User::create(['first_name' => 'Admin', 'last_name' => 'Test', 'email' => $email, 'password' => 'Test-only-2026!', 'role' => 'admin']);
    }
    private function allow(User $user, array $names): void
    {
        foreach ($names as $name) $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
    }
    private function panel(): void { Filament::setCurrentPanel(Filament::getPanel('admin')); }

    public function test_existing_users_are_not_assigned_or_stripped_of_permissions(): void
    {
        $user = $this->account(); $this->allow($user, ['confirm_bank_payment']);
        $this->assertNull($user->admin_department_id);
        $this->assertTrue($user->can('confirm_bank_payment'));
        $this->assertDatabaseCount('admin_departments', 4);
    }
    public function test_department_caps_direct_and_role_permissions_without_granting_new_ones(): void
    {
        $user = $this->account();
        $permission = Permission::findOrCreate('confirm_bank_payment', 'web');
        $role = Role::findOrCreate('legacy_accountant', 'web'); $role->givePermissionTo($permission); $user->assignRole($role);
        $this->allow($user, ['review_subscription_compliance']);
        $dept = AdminDepartment::where('name', 'Conformité / KYC')->firstOrFail();
        $user->forceFill(['admin_department_id' => $dept->id])->save();
        $this->assertFalse($user->can('confirm_bank_payment'));
        $this->assertFalse($user->hasPermissionTo($permission));
        $this->assertTrue($user->can('review_subscription_compliance'));
        Permission::findOrCreate('access_admin_panel', 'web');
        $this->assertFalse($user->can('access_admin_panel'));
        $this->allow($user, ['access_admin_panel']);
        $this->assertTrue($user->can('access_admin_panel'));
        $dept->update(['is_active' => false]);
        $this->assertFalse($user->can('review_subscription_compliance'));
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }
    public function test_super_admin_keeps_access_when_department_is_disabled(): void
    {
        $user = $this->account(); $user->assignRole(Role::findOrCreate('super_admin', 'web'));
        $dept = AdminDepartment::first(); $dept->update(['is_active' => false]);
        $user->forceFill(['admin_department_id' => $dept->id])->save();
        $this->assertTrue($user->can('confirm_bank_payment'));
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
    }
    public function test_financial_service_rejects_wrong_department_before_touching_transaction(): void
    {
        $user = $this->account(); $this->allow($user, ['confirm_bank_payment']);
        $user->forceFill(['admin_department_id' => AdminDepartment::where('name', 'Support client')->value('id')])->save();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(BankPaymentService::class)->confirm(new Subscription(), $user, []);
    }
    public function test_only_super_admin_can_manage_departments_and_read_access_log(): void
    {
        $this->panel(); $user = $this->account(); $this->allow($user, ['access_admin_panel']);
        Livewire::actingAs($user, 'web')->test(\App\Filament\Resources\AdminDepartmentResource\Pages\ListAdminDepartments::class)->assertForbidden();
        Livewire::actingAs($user, 'web')->test(\App\Filament\Resources\AdminAccessEventResource\Pages\ListAdminAccessEvents::class)->assertForbidden();
        $user->assignRole(Role::findOrCreate('super_admin', 'web'));
        Livewire::actingAs($user, 'web')->test(\App\Filament\Resources\AdminDepartmentResource\Pages\ListAdminDepartments::class)->assertSuccessful();
        Livewire::actingAs($user, 'web')->test(\App\Filament\Resources\AdminAccessEventResource\Pages\ListAdminAccessEvents::class)->assertSuccessful();
    }
    public function test_department_edit_is_audited_and_applies_immediately(): void
    {
        $this->panel(); $user = $this->account(); $user->assignRole(Role::findOrCreate('super_admin', 'web'));
        $dept = AdminDepartment::first();
        Livewire::actingAs($user, 'web')->test(\App\Filament\Resources\AdminDepartmentResource\Pages\EditAdminDepartment::class, ['record' => $dept->id])
            ->fillForm(['name' => 'Conformité modifiée', 'permissions' => ['view_subscription'], 'is_active' => false])
            ->call('save')->assertHasNoFormErrors();
        $this->assertFalse($dept->fresh()->is_active);
        $this->assertSame(['view_subscription'], $dept->fresh()->permissions);
        $this->assertDatabaseHas('admin_access_events', ['actor_id' => $user->id, 'event' => 'department_updated', 'department_id' => $dept->id]);
    }
    public function test_super_admin_can_assign_department_on_user_form_and_audit_it(): void
    {
        $this->panel(); $actor = $this->account(); $actor->assignRole(Role::findOrCreate('super_admin', 'web'));
        $target = $this->account('target@example.test'); $dept = AdminDepartment::first();
        Livewire::actingAs($actor, 'web')->test(\App\Filament\Resources\UserResource\Pages\EditUser::class, ['record' => $target->id])
            ->fillForm(['admin_department_id' => $dept->id])->call('save')->assertHasNoFormErrors();
        $this->assertEquals($dept->id, $target->fresh()->admin_department_id);
        $this->assertDatabaseHas('admin_access_events', ['actor_id' => $actor->id, 'user_id' => $target->id, 'event' => 'department_assigned']);
        $this->assertArrayNotHasKey('admin_department_id', $target->fresh()->toArray());
    }
    public function test_delegated_user_editor_cannot_take_over_admin_accounts(): void
    {
        $this->panel(); $actor = $this->account(); $target = $this->account('target@example.test');
        $this->allow($actor, ['access_admin_panel', 'view_any_user', 'view_user', 'update_user']);
        Livewire::actingAs($actor, 'web')->test(\App\Filament\Resources\UserResource\Pages\EditUser::class, ['record' => $target->id])->assertForbidden();
        $target->update(['role' => 'client']);
        $page = Livewire::actingAs($actor, 'web')->test(\App\Filament\Resources\UserResource\Pages\EditUser::class, ['record' => $target->id])->assertSuccessful();
        $page->set('data.role', 'admin')->set('data.admin_department_id', AdminDepartment::first()->id)->call('save')->assertHasNoFormErrors();
        $this->assertSame('client', $target->fresh()->role);
        $this->assertNull($target->fresh()->admin_department_id);
    }
    public function test_departments_also_restrict_previously_unprotected_resources(): void
    {
        $user = $this->account();
        $this->assertTrue($user->can('viewAny', \App\Models\BankDetail::class));
        $user->forceFill(['admin_department_id' => AdminDepartment::where('name', 'Support client')->value('id')])->save();
        $this->allow($user, ['view_any_bank_detail', 'create_product_vl']);
        $this->assertFalse($user->can('viewAny', \App\Models\BankDetail::class));
        $this->assertFalse($user->can('create', \App\Models\ProductVl::class));
        $user->forceFill(['admin_department_id' => AdminDepartment::where('name', 'Gestion des fonds')->value('id')])->save();
        $this->assertTrue($user->can('create', \App\Models\ProductVl::class));
    }

}