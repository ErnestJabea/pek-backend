<?php

namespace Tests\Feature;

use App\Filament\Actions\TableExcelExportAction;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ClientResource\Pages\ViewClientPortfolioReport;
use App\Filament\Resources\OnboardingSessionResource\Pages\ViewOnboardingSession;
use App\Filament\Resources\SubscriptionResource;
use App\Models\AdminDepartment;
use App\Models\Client;
use App\Models\OnboardingEvent;
use App\Models\OnboardingSession;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MakerCheckerAndBackofficeImprovementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config(['identity_verification.required' => false]);
        Mail::fake();
        Storage::fake('kyc_private');
    }

    public function test_subscription_query_includes_all_records_without_restriction(): void
    {
        $product = Product::create([
            'libelle' => 'FCP Test',
            'code' => 'FCP-TEST',
            'vl' => 10000,
            'seuil_minimum' => 50000,
            'is_active' => true,
        ]);

        // 1. Client normal
        $client = User::create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@test.local',
            'password' => bcrypt('Password123!'),
            'role' => 'client',
        ]);

        Subscription::create([
            'user_id' => $client->id,
            'product_id' => $product->id,
            'montant_total' => 100000,
            'investment_amount' => 99000,
            'subscription_fee' => 1000,
            'prix_unitaire' => 10000,
            'moyen_paiement' => 'bank_transfer',
            'statut' => 'Succès',
            'reference_transaction' => 'SUB-JEAN-01',
            'nb_parts' => 10,
        ]);

        // 2. Client soft-deleted
        $deletedClient = User::create([
            'first_name' => 'Paul',
            'last_name' => 'Archive',
            'email' => 'paul.archive@test.local',
            'password' => bcrypt('Password123!'),
            'role' => 'client',
        ]);
        Subscription::create([
            'user_id' => $deletedClient->id,
            'product_id' => $product->id,
            'montant_total' => 50000,
            'investment_amount' => 49500,
            'subscription_fee' => 500,
            'prix_unitaire' => 10000,
            'moyen_paiement' => 'bank_transfer',
            'statut' => 'Succès',
            'reference_transaction' => 'SUB-ARCHIVE-01',
            'nb_parts' => 5,
        ]);
        $deletedClient->delete(); // soft delete

        // 3. User sans rôle client explicite
        $otherUser = User::create([
            'first_name' => 'Claire',
            'last_name' => 'Autre',
            'email' => 'claire.autre@test.local',
            'password' => bcrypt('Password123!'),
            'role' => 'lead',
        ]);
        Subscription::create([
            'user_id' => $otherUser->id,
            'product_id' => $product->id,
            'montant_total' => 75000,
            'investment_amount' => 74250,
            'subscription_fee' => 750,
            'prix_unitaire' => 10000,
            'moyen_paiement' => 'bank_transfer',
            'statut' => 'En attente',
            'reference_transaction' => 'SUB-AUTRE-01',
            'nb_parts' => 7.5,
        ]);

        $query = SubscriptionResource::getEloquentQuery();
        $this->assertSame(3, $query->count(), 'All subscriptions must be included in the query regardless of user scope or soft-delete.');
    }

    public function test_table_excel_export_action_form_schema_and_file_generation(): void
    {
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin.export@test.local',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
        ]);
        $superAdmin->assignRole('super_admin');
        $this->actingAs($superAdmin);

        $product = Product::create([
            'libelle' => 'FCP Sérénité',
            'code' => 'FCP-SERENITE',
            'vl' => 10000,
            'seuil_minimum' => 50000,
            'is_active' => true,
        ]);

        $client = User::create([
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'email' => 'alice.martin@test.local',
            'password' => bcrypt('Password123!'),
            'role' => 'client',
        ]);

        Subscription::create([
            'user_id' => $client->id,
            'product_id' => $product->id,
            'montant_total' => 250000,
            'investment_amount' => 247500,
            'subscription_fee' => 2500,
            'prix_unitaire' => 10000,
            'moyen_paiement' => 'bank_transfer',
            'statut' => 'Succès',
            'reference_transaction' => 'SUB-EXPORT-01',
            'nb_parts' => 25,
        ]);

        $livewire = new \App\Filament\Resources\SubscriptionResource\Pages\ListSubscriptions();
        $table = new Table($livewire);
        $configuredTable = SubscriptionResource::table($table);

        $schema = TableExcelExportAction::getFormSchema($configuredTable);
        $this->assertCount(2, $schema);

        $checkboxList = $schema[0];
        $options = $checkboxList->getOptions();
        $this->assertArrayHasKey('montant_total', $options);
        $this->assertArrayHasKey('user.last_name', $options);

        // Test generation of XLSX file
        $response = TableExcelExportAction::exportFromTable([
            'columns' => ['user.last_name', 'product.libelle', 'montant_total', 'statut'],
            'format' => 'xlsx',
            'filename' => 'test-export-souscriptions',
        ], $configuredTable, $livewire);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
        $this->assertFileExists($response->getFile()->getPathname());
        $this->assertGreaterThan(1000, filesize($response->getFile()->getPathname()));
    }

    public function test_maker_checker_creator_cannot_validate_own_onboarding_session(): void
    {
        $complianceDept = AdminDepartment::firstOrCreate(
            ['name' => 'Conformité / KYC'],
            [
                'description' => 'Département conformité',
                'is_active' => true,
                'permissions' => ['access_admin_panel', 'view_any_onboarding::session', 'view_onboarding::session', 'update_onboarding::session'],
            ]
        );
        $complianceDept->update([
            'permissions' => ['access_admin_panel', 'view_any_onboarding::session', 'view_onboarding::session', 'update_onboarding::session'],
        ]);

        $makerAgent = User::create([
            'first_name' => 'Agent',
            'last_name' => 'Saisie',
            'email' => 'agent.saisie@kori.test',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
            'admin_department_id' => $complianceDept->id,
        ]);
        foreach (['access_admin_panel', 'view_any_onboarding::session', 'view_onboarding::session', 'update_onboarding::session'] as $perm) {
            $makerAgent->givePermissionTo(Permission::findOrCreate($perm, 'web'));
        }

        $client = User::create([
            'first_name' => 'Dossier',
            'last_name' => 'Client',
            'email' => 'client.dossier@test.local',
            'password' => bcrypt('Password123!'),
            'role' => 'client',
        ]);

        $session = $client->onboardingSession()->create([
            'status' => 'completed',
            'current_step' => 'completed',
            'payload' => [
                'created_by_user_id' => $makerAgent->id,
            ],
        ]);

        OnboardingEvent::create([
            'onboarding_session_id' => $session->id,
            'actor_user_id' => $makerAgent->id,
            'event_type' => 'created',
            'from_status' => 'not_started',
            'to_status' => 'completed',
        ]);

        // Maker agent is the creator -> Action "validate" is strictly disabled
        Livewire::actingAs($makerAgent)
            ->test(ViewOnboardingSession::class, ['record' => $session->getRouteKey()])
            ->assertActionDisabled('validate');

        $this->assertSame('completed', $session->fresh()->status);
    }

    public function test_maker_checker_another_compliance_agent_can_validate_session(): void
    {
        $complianceDept = AdminDepartment::firstOrCreate(
            ['name' => 'Conformité / KYC'],
            [
                'description' => 'Département conformité',
                'is_active' => true,
                'permissions' => ['access_admin_panel', 'view_any_onboarding::session', 'view_onboarding::session', 'update_onboarding::session'],
            ]
        );
        $complianceDept->update([
            'permissions' => ['access_admin_panel', 'view_any_onboarding::session', 'view_onboarding::session', 'update_onboarding::session'],
        ]);

        $makerAgent = User::create([
            'first_name' => 'Agent',
            'last_name' => 'Un',
            'email' => 'agent.un@kori.test',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
            'admin_department_id' => $complianceDept->id,
        ]);

        $checkerAgent = User::create([
            'first_name' => 'Agent',
            'last_name' => 'Deux',
            'email' => 'agent.deux@kori.test',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
            'admin_department_id' => $complianceDept->id,
        ]);

        foreach (['access_admin_panel', 'view_any_onboarding::session', 'view_onboarding::session', 'update_onboarding::session'] as $perm) {
            $makerAgent->givePermissionTo(Permission::findOrCreate($perm, 'web'));
            $checkerAgent->givePermissionTo(Permission::findOrCreate($perm, 'web'));
        }

        $client = User::create([
            'first_name' => 'Dossier',
            'last_name' => 'Deuxieme',
            'email' => 'client.deuxieme@test.local',
            'password' => bcrypt('Password123!'),
            'role' => 'client',
        ]);

        $session = $client->onboardingSession()->create([
            'status' => 'completed',
            'current_step' => 'completed',
            'payload' => [
                'created_by_user_id' => $makerAgent->id,
            ],
        ]);

        OnboardingEvent::create([
            'onboarding_session_id' => $session->id,
            'actor_user_id' => $makerAgent->id,
            'event_type' => 'created',
            'from_status' => 'not_started',
            'to_status' => 'completed',
        ]);

        // Checker agent validates -> Action is enabled and succeeds
        Livewire::actingAs($checkerAgent)
            ->test(ViewOnboardingSession::class, ['record' => $session->getRouteKey()])
            ->assertActionEnabled('validate')
            ->callAction('validate')
            ->assertNotified('Dossier d\'onboarding validé avec succès !');

        $this->assertSame('validated', $session->fresh()->status);
    }

    public function test_maker_checker_non_compliance_department_user_cannot_validate(): void
    {
        $salesDept = AdminDepartment::firstOrCreate(
            ['name' => 'Support client'],
            [
                'description' => 'Département commercial',
                'is_active' => true,
                'permissions' => ['access_admin_panel', 'view_any_onboarding::session', 'view_onboarding::session'],
            ]
        );
        $salesDept->update([
            'permissions' => ['access_admin_panel', 'view_any_onboarding::session', 'view_onboarding::session'],
        ]);

        $salesUser = User::create([
            'first_name' => 'Commercial',
            'last_name' => 'Commercial',
            'email' => 'commercial@kori.test',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
            'admin_department_id' => $salesDept->id,
        ]);
        foreach (['access_admin_panel', 'view_any_onboarding::session', 'view_onboarding::session'] as $perm) {
            $salesUser->givePermissionTo(Permission::findOrCreate($perm, 'web'));
        }

        $client = User::create([
            'first_name' => 'Client',
            'last_name' => 'Externe',
            'email' => 'client.externe@test.local',
            'password' => bcrypt('Password123!'),
            'role' => 'client',
        ]);

        $session = $client->onboardingSession()->create([
            'status' => 'completed',
            'current_step' => 'completed',
            'payload' => [],
        ]);

        // Sales user can view the dossier but validate is forbidden (403)
        Livewire::actingAs($salesUser)
            ->test(ViewOnboardingSession::class, ['record' => $session->getRouteKey()])
            ->mountAction('validate')
            ->call('callMountedAction')
            ->assertForbidden();

        $this->assertSame('completed', $session->fresh()->status);
    }

    public function test_client_row_url_and_monthly_transaction_grouping(): void
    {
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin.report@test.local',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
        ]);
        $superAdmin->assignRole('super_admin');
        $this->actingAs($superAdmin);

        $product = Product::create([
            'libelle' => 'FCP Kori Sérénité',
            'code' => 'KORI-SER',
            'vl' => 10000,
            'seuil_minimum' => 50000,
            'is_active' => true,
        ]);

        $client = Client::create([
            'first_name' => 'Marc',
            'last_name' => 'Ewondo',
            'email' => 'marc.ewondo@test.local',
            'password' => bcrypt('Password123!'),
            'role' => 'client',
        ]);

        // Souscription en Septembre 2026
        Subscription::create([
            'user_id' => $client->id,
            'product_id' => $product->id,
            'montant_total' => 101000,
            'investment_amount' => 100000,
            'subscription_fee' => 1000,
            'prix_unitaire' => 10000,
            'moyen_paiement' => 'bank_transfer',
            'nb_parts' => 10,
            'statut' => 'Succès',
            'reference_transaction' => 'SUB-SEP-01',
            'value_date' => '2026-09-15',
            'created_at' => '2026-09-15 10:00:00',
        ]);

        // Souscription en Octobre 2026
        Subscription::create([
            'user_id' => $client->id,
            'product_id' => $product->id,
            'montant_total' => 202000,
            'investment_amount' => 200000,
            'subscription_fee' => 2000,
            'prix_unitaire' => 10000,
            'moyen_paiement' => 'bank_transfer',
            'nb_parts' => 20,
            'statut' => 'Succès',
            'reference_transaction' => 'SUB-OCT-01',
            'value_date' => '2026-10-10',
            'created_at' => '2026-10-10 14:00:00',
        ]);

        // Test Livewire component of ViewClientPortfolioReport
        $component = Livewire::actingAs($superAdmin)
            ->test(ViewClientPortfolioReport::class, ['record' => $client->getRouteKey()]);

        $component->assertSuccessful();

        $monthlyGroups = $component->get('transactionsGroupedByMonth');
        $this->assertArrayHasKey('2026-10', $monthlyGroups);
        $this->assertArrayHasKey('2026-09', $monthlyGroups);

        $this->assertSame(202000.0, $monthlyGroups['2026-10']['total_brut']);
        $this->assertSame(2000.0, $monthlyGroups['2026-10']['total_frais']);
        $this->assertSame(200000.0, $monthlyGroups['2026-10']['total_net']);
        $this->assertSame(20.0, $monthlyGroups['2026-10']['total_parts']);

        $this->assertSame(101000.0, $monthlyGroups['2026-09']['total_brut']);
        $this->assertSame(1000.0, $monthlyGroups['2026-09']['total_frais']);
        $this->assertSame(100000.0, $monthlyGroups['2026-09']['total_net']);
        $this->assertSame(10.0, $monthlyGroups['2026-09']['total_parts']);
    }
}
