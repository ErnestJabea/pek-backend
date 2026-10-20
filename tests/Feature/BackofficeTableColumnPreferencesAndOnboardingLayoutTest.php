<?php

namespace Tests\Feature;

use App\Models\OnboardingSession;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class BackofficeTableColumnPreferencesAndOnboardingLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_agent_kam_is_automatically_assigned_from_authenticated_user_when_creating_session()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'first_name' => 'Alice',
            'last_name' => 'Compliance',
            'email' => 'alice.compliance@example.com',
        ]);
        $admin->assignRole('super_admin');

        $client = User::factory()->create([
            'role' => 'client',
            'first_name' => 'Ernest',
            'last_name' => 'Jabea',
            'email' => 'ernest.client@example.com',
        ]);

        $this->actingAs($admin);

        $createPage = new \App\Filament\Resources\OnboardingSessionResource\Pages\CreateOnboardingSession();
        $reflection = new \ReflectionClass($createPage);
        $method = $reflection->getMethod('mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $formData = [
            'user_id' => $client->id,
            'status' => 'validated',
            'risk_level' => 'LOW',
            'payload' => [
                'nature_client' => 'personne_physique',
                'nom' => 'Jabea',
                'prenom' => 'Ernest',
                'civ' => 'M.',
                // agent_kam left completely empty!
            ],
        ];

        $mutated = $method->invoke($createPage, $formData);

        $this->assertNotEmpty($mutated['payload']['agent_kam']);
        $this->assertStringContainsString('Alice Compliance', $mutated['payload']['agent_kam']);
    }

    public function test_nature_personne_morale_cleanses_physical_attributes_and_keeps_rccm()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'first_name' => 'Alice',
            'last_name' => 'Compliance',
        ]);
        $admin->assignRole('super_admin');

        $client = User::factory()->create([
            'role' => 'client',
            'first_name' => 'Societe',
            'last_name' => 'Alpha',
        ]);

        $this->actingAs($admin);

        $createPage = new \App\Filament\Resources\OnboardingSessionResource\Pages\CreateOnboardingSession();
        $reflection = new \ReflectionClass($createPage);
        $method = $reflection->getMethod('mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $formData = [
            'user_id' => $client->id,
            'status' => 'validated',
            'risk_level' => 'LOW',
            'payload' => [
                'nature_client' => 'personne_morale',
                'nom' => 'ALPHA HOLDING SAS',
                'prenom' => 'Jean Directeur',
                'rccm' => 'RC/DLA/2024/B/999',
                'civ' => 'M.', // was filled inadvertently
                'situation_mat' => 'Marié(e)',
                'profession' => 'Ingénieur',
            ],
        ];

        $mutated = $method->invoke($createPage, $formData);

        $this->assertSame('ALPHA HOLDING SAS', $mutated['payload']['nom']);
        $this->assertSame('RC/DLA/2024/B/999', $mutated['payload']['rccm']);
        $this->assertNull($mutated['payload']['civ']);
        $this->assertNull($mutated['payload']['situation_mat']);
        $this->assertNull($mutated['payload']['profession']);
    }

    public function test_user_table_preferences_are_synced_to_and_from_database_per_user()
    {
        $user1 = User::factory()->create([
            'role' => 'admin',
            'email' => 'user1@example.com',
            'table_preferences' => [
                'abc123hash' => ['phone' => true, 'country' => false],
            ],
        ]);

        $user2 = User::factory()->create([
            'role' => 'admin',
            'email' => 'user2@example.com',
            'table_preferences' => [
                'abc123hash' => ['phone' => false, 'country' => true],
            ],
        ]);

        $middleware = new \App\Http\Middleware\SyncUserTablePreferences();
        $request = Request::create('/admin/clients', 'GET');

        // Test User 1 pre-load
        $this->actingAs($user1);
        session()->flush();

        $middleware->handle($request, function ($req) {
            $sessionKey = 'tables.abc123hash_toggled_columns';
            $this->assertTrue(session()->has($sessionKey));
            $this->assertEquals(['phone' => true, 'country' => false], session()->get($sessionKey));

            // Simulate user changing toggles in this request
            session()->put($sessionKey, ['phone' => false, 'country' => false, 'city' => true]);

            return new Response();
        });

        // Verify user 1 DB preferences were updated
        $user1->refresh();
        $this->assertEquals(['phone' => false, 'country' => false, 'city' => true], $user1->table_preferences['abc123hash']);

        // Test User 2 remains completely isolated and untouched!
        $user2->refresh();
        $this->assertEquals(['phone' => false, 'country' => true], $user2->table_preferences['abc123hash']);
    }
}
