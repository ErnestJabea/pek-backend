<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageStatementSettings;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageStatementSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_access_and_save_statement_settings(): void
    {
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create([
            'email' => 'ejabbing@pek.com',
            'role' => 'admin',
        ]);
        $admin->assignRole($superAdminRole);

        $this->actingAs($admin);

        $this->assertTrue(ManageStatementSettings::canAccess());

        Livewire::test(ManageStatementSettings::class)
            ->assertSuccessful()
            ->set('data.monthly_statement_pwa_enabled', true)
            ->set('data.monthly_statement_history_months', 36)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue((bool) SystemSetting::get('monthly_statement_pwa_enabled'));
        $this->assertEquals(36, (int) SystemSetting::get('monthly_statement_history_months'));
    }

    public function test_regular_user_cannot_access_statement_settings(): void
    {
        $user = User::factory()->create([
            'email' => 'client@test.com',
            'role' => 'user',
        ]);

        $this->actingAs($user);

        $this->assertFalse(ManageStatementSettings::canAccess());
    }
}
