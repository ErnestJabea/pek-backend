<?php

namespace Tests\Feature;

use App\Filament\Pages\ClientBirthdays;
use App\Filament\Pages\IdDocumentExpirations;
use App\Filament\Resources\ProfileChangeRequestResource;
use App\Models\AdminDepartment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RubricAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(string $email = 'admin-rubric@example.test'): User
    {
        return User::create([
            'first_name' => 'Rubric',
            'last_name' => 'Tester',
            'email' => $email,
            'password' => 'Test-only-2026!',
            'role' => 'admin',
        ]);
    }

    private function allow(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
    }

    private function setAdminPanel(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_client_birthdays_page_is_strictly_controlled_by_permission(): void
    {
        $this->setAdminPanel();
        $user = $this->createAdmin();
        // User has access to admin panel and view_any_client (such as generic client viewer)
        $this->allow($user, ['access_admin_panel', 'view_any_client']);

        // Without page_ClientBirthdays, access must be DENIED even if user can view clients
        $this->actingAs($user, 'web');
        $this->assertFalse(ClientBirthdays::canAccess());
        Livewire::actingAs($user, 'web')
            ->test(ClientBirthdays::class)
            ->assertForbidden();

        // Grant page_ClientBirthdays permission
        $this->allow($user, ['page_ClientBirthdays']);
        $this->assertTrue(ClientBirthdays::canAccess());
        Livewire::actingAs($user, 'web')
            ->test(ClientBirthdays::class)
            ->assertSuccessful();
    }

    public function test_id_document_expirations_page_is_strictly_controlled_by_permission(): void
    {
        $this->setAdminPanel();
        $user = $this->createAdmin();
        // User has compliance review permission and view_any_client
        $this->allow($user, ['access_admin_panel', 'review_subscription_compliance', 'view_any_client']);

        // Without page_IdDocumentExpirations, access must be DENIED
        $this->actingAs($user, 'web');
        $this->assertFalse(IdDocumentExpirations::canAccess());
        Livewire::actingAs($user, 'web')
            ->test(IdDocumentExpirations::class)
            ->assertForbidden();

        // Grant page_IdDocumentExpirations
        $this->allow($user, ['page_IdDocumentExpirations']);
        $this->assertTrue(IdDocumentExpirations::canAccess());
        Livewire::actingAs($user, 'web')
            ->test(IdDocumentExpirations::class)
            ->assertSuccessful();
    }

    public function test_profile_change_request_resource_requires_own_permission(): void
    {
        $this->setAdminPanel();
        $user = $this->createAdmin();
        // User only has review_subscription_compliance
        $this->allow($user, ['access_admin_panel', 'review_subscription_compliance']);

        $this->actingAs($user, 'web');
        $this->assertFalse(ProfileChangeRequestResource::canViewAny());

        // Grant view_any_profile_change_request
        $this->allow($user, ['view_any_profile_change_request']);
        $this->assertTrue(ProfileChangeRequestResource::canViewAny());
    }

    public function test_department_caps_rubric_permissions_when_not_whitelisted_in_department(): void
    {
        $this->setAdminPanel();
        $user = $this->createAdmin();
        // User has direct permissions
        $this->allow($user, ['access_admin_panel', 'page_ClientBirthdays', 'view_any_client']);

        // Assign to a department that only has 'view_any_client' and 'access_admin_panel'
        $department = AdminDepartment::create([
            'name' => 'Support Restreint',
            'permissions' => ['access_admin_panel', 'view_any_client'],
            'is_active' => true,
        ]);
        $user->forceFill(['admin_department_id' => $department->id])->save();

        $this->actingAs($user, 'web');
        // Department caps permissions: page_ClientBirthdays is not whitelisted, so user->can() is false
        $this->assertFalse($user->can('page_ClientBirthdays'));
        $this->assertFalse(ClientBirthdays::canAccess());

        // Whitelist page_ClientBirthdays in department
        $department->update([
            'permissions' => ['access_admin_panel', 'view_any_client', 'page_ClientBirthdays'],
        ]);

        $this->assertTrue($user->fresh()->can('page_ClientBirthdays'));
        $this->assertTrue(ClientBirthdays::canAccess());
    }

    public function test_super_admin_bypasses_all_rubric_restrictions(): void
    {
        $this->setAdminPanel();
        $superAdmin = $this->createAdmin('superadmin-rubric@example.test');
        $superAdmin->assignRole(Role::findOrCreate('super_admin', 'web'));

        $this->actingAs($superAdmin, 'web');
        $this->assertTrue(ClientBirthdays::canAccess());
        $this->assertTrue(IdDocumentExpirations::canAccess());
        $this->assertTrue(ProfileChangeRequestResource::canViewAny());
    }
}
