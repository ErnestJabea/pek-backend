<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\OnboardingSessionResource\Pages\ViewOnboardingSession;
use App\Mail\OnboardingValidatedMail;
use App\Models\OnboardingSession;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackofficeManualOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['identity_verification.required' => false]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Mail::fake();
    }

    public function test_view_onboarding_session_can_validate_without_sending_email(): void
    {
        $admin = User::factory()->create([
            'email' => 'compliance.admin@koriassetmanagement.com',
            'role' => 'super_admin',
        ]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        $client = User::factory()->create([
            'email' => 'client.historique@koriassetmanagement.com',
            'first_name' => 'Paul',
            'last_name' => 'Biya',
            'role' => 'client',
        ]);

        $session = OnboardingSession::create([
            'user_id' => $client->id,
            'current_step' => 'completed',
            'status' => 'completed',
            'payload' => [
                'nom' => 'Biya',
                'prenom' => 'Paul',
                'email' => 'client.historique@koriassetmanagement.com',
            ],
        ]);

        // Simuler la validation via ViewOnboardingSession avec send_notification_email = false
        Livewire::actingAs($admin)
            ->test(ViewOnboardingSession::class, ['record' => $session->getRouteKey()])
            ->callAction('validate', [
                'send_notification_email' => false,
                'create_in_app_notification' => false,
            ])
            ->assertHasNoActionErrors();

        $session->refresh();
        $client->refresh();

        $this->assertEquals('validated', $session->status);
        $this->assertNotNull($session->validated_at);
        $this->assertTrue($client->onboarding_completed);
        $this->assertEquals('validated', $client->onboarding_status);

        // Strictement aucun e-mail envoyé
        Mail::assertNothingSent();
    }

    public function test_view_onboarding_session_sends_email_only_when_explicitly_requested(): void
    {
        $admin = User::factory()->create([
            'email' => 'compliance2@koriassetmanagement.com',
            'role' => 'super_admin',
        ]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        $client = User::factory()->create([
            'email' => 'nouveau.client@koriassetmanagement.com',
            'first_name' => 'Marc',
            'last_name' => 'Ewondo',
            'role' => 'client',
        ]);

        $session = OnboardingSession::create([
            'user_id' => $client->id,
            'current_step' => 'completed',
            'status' => 'completed',
            'payload' => [
                'nom' => 'Ewondo',
                'prenom' => 'Marc',
            ],
        ]);

        // Exécuter l'action avec send_notification_email = true
        Livewire::actingAs($admin)
            ->test(ViewOnboardingSession::class, ['record' => $session->getRouteKey()])
            ->callAction('validate', [
                'send_notification_email' => true,
                'create_in_app_notification' => true,
            ])
            ->assertHasNoActionErrors();

        $session->refresh();
        $this->assertEquals('validated', $session->status);

        // Vérifier que l'e-mail a bien été envoyé
        Mail::assertSent(OnboardingValidatedMail::class, function ($mail) use ($client) {
            return $mail->hasTo($client->email);
        });
    }

    public function test_client_create_onboarding_action_supports_immediate_validation_without_email(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin.guichet@koriassetmanagement.com',
            'role' => 'super_admin',
        ]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        $client = User::factory()->create([
            'email' => 'client.guichet@koriassetmanagement.com',
            'first_name' => 'Alice',
            'last_name' => 'Ngono',
            'role' => 'client',
        ]);

        $this->assertFalse($client->onboarding_completed);

        Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\ClientResource\Pages\ManageClients::class)
            ->callTableAction('create_onboarding', $client, [
                'status' => 'validated',
                'categorie_client' => 'Particulier',
                'risk_profile' => 'Prudent',
                'send_email' => false,
                'send_in_app' => false,
            ])
            ->assertHasNoTableActionErrors();

        $client->refresh();
        $this->assertTrue($client->onboarding_completed);
        $this->assertEquals('validated', $client->onboarding_status);
        $this->assertEquals('Particulier', $client->categorie_client);

        $session = $client->onboardingSession;
        $this->assertNotNull($session);
        $this->assertEquals('validated', $session->status);
        $this->assertNotNull($session->validated_at);

        // Zéro e-mail envoyé
        Mail::assertNothingSent();
    }
}
