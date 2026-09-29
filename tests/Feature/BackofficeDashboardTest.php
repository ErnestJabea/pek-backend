<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\OnboardingSessionResource\Pages\ListOnboardingSessions;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\SubscriptionResource\Pages\ListSubscriptions;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Widgets\BusinessDashboard;
use App\Models\AdminDepartment;
use App\Models\OnboardingSession;
use App\Models\Product;
use App\Models\ProductVl;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BackofficeDashboard;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackofficeDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $client;
    private Product $product;
    private BackofficeDashboard $dashboard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 26)->setTime(12, 0));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->admin = $this->account('admin');
        $this->admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->client = $this->account('client');
        $this->product = Product::create(['libelle' => 'Fonds test', 'description' => 'Test', 'vl' => 10000, 'seuil_minimum' => 10000, 'is_active' => true]);
        $this->dashboard = app(BackofficeDashboard::class);
        $this->actingAs($this->admin);
    }

    private function account(string $role): User
    {
        return User::create(['first_name' => 'Test', 'last_name' => 'Dashboard', 'email' => uniqid().'@example.test',
            'password' => 'Local-test-only-2026!', 'role' => $role]);
    }

    private function subscription(array $attributes = []): Subscription
    {
        return Subscription::withoutEvents(fn () => Subscription::forceCreate(array_merge([
            'user_id' => $this->client->id, 'product_id' => $this->product->id,
            'nb_parts' => 1, 'prix_unitaire' => 10000, 'montant_total' => 10100,
            'moyen_paiement' => 'bank_transfer', 'statut' => 'En attente',
        ], $attributes)));
    }

    private function values(): array
    {
        return collect($this->dashboard->snapshot($this->admin))->flatten(1)->pluck('value', 'key')->all();
    }

    public function test_common_metrics_use_business_midnight_and_exclude_staff(): void
    {
        $this->subscription(['created_at' => '2026-09-25 22:59:59']);
        $this->subscription(['created_at' => '2026-09-25 23:00:00']);
        $this->subscription(['created_at' => '2026-09-26 22:59:59']);
        $this->subscription(['created_at' => '2026-09-26 23:00:00']);
        $delegated = $this->account('client');
        $delegated->givePermissionTo(Permission::findOrCreate('access_admin_panel', 'web'));
        $staff = $this->account('client');
        $role = Role::findOrCreate('delegated', 'web');
        $role->givePermissionTo(Permission::findOrCreate('access_admin_panel', 'web'));
        $staff->assignRole($role);
        $this->assertEquals(2, $this->values()['subscriptions_today']);
        $this->assertEquals(1, $this->values()['registrations_today']);
    }

    public function test_amounts_follow_value_date_then_receipt_confirmation_and_legacy_creation(): void
    {
        $this->subscription(['statut' => 'Succès', 'value_date' => '2026-09-26', 'created_at' => now()->subDays(3)]);
        $this->subscription(['statut' => 'Succès', 'value_date' => '2026-09-25', 'payment_confirmed_at' => now()]);
        $this->subscription(['statut' => 'Succès', 'funds_received_at' => '2026-09-25 23:00:00']);
        $this->subscription(['statut' => 'Succès', 'payment_confirmed_at' => now()]);
        $this->subscription(['statut' => 'Succès']); // Legacy record.
        $this->subscription(['valuation_status' => 'awaiting_nav', 'funds_received_at' => now()]);
        $this->subscription(['statut' => 'Échec']);
        $this->subscription(['statut' => 'Succès', 'mobile_state' => 'reversed']);
        $this->assertEquals(40400, $this->values()['confirmed_today']);
        $this->assertEquals(50500, $this->values()['confirmed_total']);
        $this->assertEquals(1, $this->values()['accounting_awaiting_nav']);
    }

    public function test_staging_and_simulations_never_inflate_common_metrics(): void
    {
        $real = $this->subscription(['statut' => 'Succès', 's3p_context' => ['simulation' => false, 'base_url' => 'https://production.example.test']]);
        foreach ([
            ['valuation_status' => 'staging_only'],
            ['mobile_state' => 'simulation_review'],
            ['s3p_quote_id' => 'SIM-QUOTE-123'],
            ['s3p_context' => ['simulation' => true]],
            ['s3p_context' => ['base_url' => 'https://s3p.smobilpay.staging.maviance.info']],
        ] as $attributes) $this->subscription(array_merge(['statut' => 'Succès'], $attributes));
        $this->assertEquals(1, $this->values()['subscriptions_today']);
        $this->assertEquals(10100, $this->values()['confirmed_total']);
        Livewire::test(ListSubscriptions::class)->filterTable('dashboard', 'confirmed_total')
            ->assertCanSeeTableRecords([$real])->assertCountTableRecords(1);
    }

    public function test_each_department_sees_only_its_effective_business_metrics(): void
    {
        $groups = [
            'Conformité / KYC' => 'Conformité / KYC',
            'Comptabilité / Trésorerie' => 'Comptabilité / Trésorerie',
            'Gestion des fonds' => 'Gestion des fonds',
            'Support client' => 'Support client',
        ];
        foreach ($groups as $name => $group) {
            $user = $this->account('admin');
            $department = AdminDepartment::where('name', $name)->firstOrFail();
            $user->forceFill(['admin_department_id' => $department->id])->save();
            foreach ($department->permissions as $permission) $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
            $this->actingAs($user);
            $this->assertSame(['Activité commune', $group], array_keys($this->dashboard->snapshot($user)));
            Livewire::test(BusinessDashboard::class)->assertSuccessful()->assertSee($group);
        }
    }

    public function test_department_changes_and_disable_apply_to_livewire_refresh(): void
    {
        $user = $this->account('admin');
        $department = AdminDepartment::where('name', 'Comptabilité / Trésorerie')->firstOrFail();
        $user->forceFill(['admin_department_id' => $department->id])->save();
        foreach ($department->permissions as $permission) $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        $widget = Livewire::actingAs($user)->test(BusinessDashboard::class)->assertSee('Virements à confirmer');
        $department->update(['permissions' => ['access_admin_panel', 'view_any_subscription']]);
        $widget->call('$refresh')->assertDontSee('Virements à confirmer');
        $department->update(['is_active' => false]);
        $widget->call('$refresh')->assertForbidden();
    }

    public function test_common_cards_do_not_grant_access_to_underlying_records(): void
    {
        $user = $this->account('admin');
        $this->actingAs($user);
        $groups = $this->dashboard->snapshot($user);
        $this->assertSame(['Activité commune'], array_keys($groups));
        $this->assertCount(4, $groups['Activité commune']);
        foreach ($groups['Activité commune'] as $card) $this->assertNull($card['url']);
        Livewire::test(ListSubscriptions::class)->assertForbidden();
    }

    public function test_forged_business_filter_is_rejected(): void
    {
        $user = $this->account('admin');
        $user->givePermissionTo(Permission::findOrCreate('view_any_subscription', 'web'));
        Livewire::actingAs($user)->test(ListSubscriptions::class)
            ->filterTable('dashboard', 'bank_pending')->assertForbidden();
    }

    public function test_subscription_cards_open_matching_filtered_lists(): void
    {
        $pending = $this->subscription();
        $paid = $this->subscription(['statut' => 'Succès', 'funds_received_at' => now()]);
        $failed = $this->subscription(['statut' => 'Échec']);
        $awaiting = $this->subscription(['valuation_status' => 'awaiting_nav', 'funds_received_at' => now()]);
        $this->assertEquals(10100, $this->values()['bank_pending_amount']);
        foreach (['bank_pending' => $pending, 'confirmed_total' => $paid, 'failures_today' => $failed, 'accounting_awaiting_nav' => $awaiting] as $key => $record) {
            Livewire::test(ListSubscriptions::class)->filterTable('dashboard', $key)
                ->assertCanSeeTableRecords([$record])->assertCountTableRecords(1);
        }
    }

    public function test_user_kyc_and_product_filters_match_cards(): void
    {
        $this->account('admin');
        Livewire::test(ListUsers::class)->filterTable('dashboard', 'registrations_today')
            ->assertCanSeeTableRecords([$this->client])->assertCountTableRecords(1);
        Livewire::test(ListUsers::class)->filterTable('dashboard', 'clients_without_subscription')
            ->assertCanSeeTableRecords([$this->client])->assertCountTableRecords(1);
        $kyc = OnboardingSession::create(['user_id' => $this->client->id, 'status' => 'completed', 'current_step' => 'completed', 'payload' => []]);
        Livewire::test(ListOnboardingSessions::class)->filterTable('dashboard', 'kyc_pending')
            ->assertCanSeeTableRecords([$kyc])->assertCountTableRecords(1);
        Livewire::test(ListProducts::class)->filterTable('dashboard', 'missing_nav')
            ->assertCanSeeTableRecords([$this->product])->assertCountTableRecords(1);
        ProductVl::create(['product_id' => $this->product->id, 'vl' => 10000, 'date_vl' => '2026-09-26']);
        $this->assertEquals(0, $this->values()['missing_nav']);
        Livewire::test(ListProducts::class)->filterTable('dashboard', 'missing_nav')->assertCountTableRecords(0);
    }

    public function test_dashboard_route_renders_and_rejects_clients_and_guests(): void
    {
        $this->get(Dashboard::getUrl())->assertOk()->assertSee('Souscriptions du jour')->assertSee('Africa/Douala');
        Livewire::test(BusinessDashboard::class)->assertSee('0 FCFA')->call('$refresh')->assertSuccessful();
        Livewire::actingAs($this->client)->test(BusinessDashboard::class)->assertForbidden();
        $this->actingAs($this->client)->get(Dashboard::getUrl())->assertForbidden();
        auth()->logout();
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_card_link_query_string_applies_the_filter_on_initial_load(): void
    {
        $this->subscription(['statut' => 'Succès']);
        $this->subscription(['statut' => 'Échec']);
        $card = collect($this->dashboard->snapshot($this->admin)['Activité commune'])->firstWhere('key', 'confirmed_total');
        parse_str(parse_url($card['url'], PHP_URL_QUERY), $query);
        Livewire::withQueryParams($query)->test(ListSubscriptions::class)
            ->assertSet('tableFilters.dashboard.value', 'confirmed_total')->assertCountTableRecords(1);
    }
}
