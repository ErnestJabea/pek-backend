<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\OnboardingSessionResource\Pages\CreateOnboardingSession;
use App\Filament\Resources\OnboardingSessionResource\Pages\EditOnboardingSession;
use App\Filament\Resources\OnboardingSessionResource\Pages\ViewOnboardingSession;
use App\Mail\OnboardingValidatedMail;
use App\Models\OnboardingSession;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

    public function test_admin_can_create_onboarding_session_with_full_kyc_risk_labft_and_signature(): void
    {
        Storage::fake('kyc_private');

        $admin = User::factory()->create([
            'email' => 'admin.create@koriassetmanagement.com',
            'role' => 'super_admin',
        ]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        $client = User::factory()->create([
            'email' => 'client.complet@koriassetmanagement.com',
            'first_name' => 'Roger',
            'last_name' => 'Tchinda',
            'role' => 'client',
        ]);

        Livewire::actingAs($admin)
            ->test(CreateOnboardingSession::class)
            ->fillForm([
                'user_id' => $client->id,
                'status' => 'validated',
                'risk_level' => 'LOW',
                'payload' => [
                    'civ' => 'M.',
                    'nom' => 'Tchinda',
                    'prenom' => 'Roger',
                    'nature_client' => 'personne_physique',
                    'categorie_client' => 'Salarié secteur privé',
                    'situation_mat' => 'Marié(e)',
                    'nat' => 'Camerounaise',
                    'dob' => '1985-05-15',
                    'lieu_naiss' => 'Douala',
                    'profession' => 'Ingénieur Télécom',
                    'employeur' => 'Orange Cameroun',
                    'tel' => '+237699001122',
                    'email' => 'client.complet@koriassetmanagement.com',
                    'adresse' => 'Bonanjo Rue Hydrocarbures',
                    'pays_residence' => 'Cameroun',
                    'agent_kam' => 'KAM-007',
                    'piece' => 'CNI',
                    'num_piece' => '1122334455',
                    'expiration_piece' => '2030-12-31',
                    // Profil Investisseur
                    'tranche_revenus' => 'plus_1_5m',
                    'epargne_possible' => 'Oui',
                    'niveau_risque' => 'max',
                    'conscience_risque' => 'Oui',
                    'objectif_invest' => 'croissance',
                    'horizon_terme' => 'long_terme',
                    'niveau_perf' => 'elevee',
                    'connaissance_marche' => 'excellente',
                    'invest_anterieurs' => 'Oui',
                    // Questionnaire LAB-FT
                    'secteur' => 'Télécommunications',
                    'revenus_annuels' => 'plus_15m',
                    'origine_fonds' => 'Épargne salariale',
                    'src_salaire' => true,
                    'banque' => 'Afriland First Bank',
                    'num_compte' => '1000500123456789',
                    'pays_compte' => 'Cameroun',
                    'pays_risque' => 'Non',
                    'secteur_sensible' => 'Non',
                    'ppe' => 'Non',
                    'condamnation' => 'Non',
                    'ack_lecture' => true,
                    'ack_donnees' => true,
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $session = OnboardingSession::where('user_id', $client->id)->first();
        $this->assertNotNull($session);
        $this->assertEquals('validated', $session->status);
        $this->assertEquals('LOW', $session->risk_level);
        $this->assertEquals('Dynamique', $session->payload['risk_profile']);
        $this->assertGreaterThanOrEqual(15, (int) $session->payload['risk_score']);

        $client->refresh();
        $this->assertEquals('M.', $client->civ);
        $this->assertEquals('Orange Cameroun', $client->employer);
        $this->assertEquals('1122334455', $client->num_piece);
        $this->assertEquals('1985-05-15', $client->dob ? $client->dob->format('Y-m-d') : null);
        $this->assertTrue($client->onboarding_completed);
    }

    public function test_admin_can_edit_onboarding_session_in_backoffice(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin.edit@koriassetmanagement.com',
            'role' => 'super_admin',
        ]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        $client = User::factory()->create([
            'email' => 'client.edit@koriassetmanagement.com',
            'first_name' => 'Jeanne',
            'last_name' => 'Mballa',
            'role' => 'client',
        ]);

        $session = OnboardingSession::create([
            'user_id' => $client->id,
            'current_step' => 'completed',
            'status' => 'completed',
            'risk_level' => 'LOW',
            'payload' => [
                'civ' => 'Mme',
                'nom' => 'Mballa',
                'prenom' => 'Jeanne',
                'nature_client' => 'personne_physique',
                'tranche_revenus' => '500k_1_5m',
                'epargne_possible' => 'Oui',
                'niveau_risque' => 'moyen',
                'conscience_risque' => 'Oui',
                'objectif_invest' => 'equilibre',
                'horizon_terme' => 'moyen_terme',
                'niveau_perf' => 'moderee',
                'connaissance_marche' => 'moyenne',
                'invest_anterieurs' => 'Non',
                'pays_risque' => 'Non',
                'secteur_sensible' => 'Non',
                'ppe' => 'Non',
                'condamnation' => 'Non',
            ],
        ]);

        // Admin édite la session et déclare un statut PPE = Oui
        Livewire::actingAs($admin)
            ->test(EditOnboardingSession::class, ['record' => $session->getRouteKey()])
            ->fillForm([
                'status' => 'validated',
                'payload.employeur' => 'Ministère des Finances',
                'payload.ppe' => 'Oui',
                'payload.ppe_detail' => 'Directeur de cabinet',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $session->refresh();
        $this->assertEquals('validated', $session->status);
        $this->assertEquals('HIGH', $session->risk_level, 'Le niveau de risque doit basculer en HIGH suite au statut PPE');
        $this->assertEquals('Oui', $session->payload['ppe']);
        $this->assertEquals('Directeur de cabinet', $session->payload['ppe_detail']);

        $client->refresh();
        $this->assertEquals('Ministère des Finances', $client->employer);
    }

    public function test_admin_can_download_uploaded_documents_and_signature(): void
    {
        Storage::fake('kyc_private');

        $admin = User::factory()->create([
            'email' => 'admin.download@koriassetmanagement.com',
            'role' => 'super_admin',
        ]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        $client = User::factory()->create([
            'email' => 'client.doc@koriassetmanagement.com',
            'first_name' => 'Samuel',
            'last_name' => 'Eto’o',
            'role' => 'client',
        ]);

        $cniPath = 'secure_onboardings/documents/test_cni.png';
        $sigPath = 'signatures/test_sig.png';
        $versoPath = 'secure_onboardings/documents/test_verso.png';

        Storage::disk('kyc_private')->put($cniPath, 'fake-cni-content');
        Storage::disk('kyc_private')->put($sigPath, 'fake-sig-content');
        Storage::disk('kyc_private')->put($versoPath, 'fake-verso-content');

        $session = OnboardingSession::create([
            'user_id' => $client->id,
            'status' => 'validated',
            'doc_piece_identite' => $cniPath,
            'signature_path' => $sigPath,
            'payload' => [
                'nom' => 'Eto’o',
                'prenom' => 'Samuel',
                'doc_piece_verso' => $versoPath,
            ],
        ]);

        // Test CNI download
        $response = $this->actingAs($admin)->get(route('admin.document.download', [
            'session' => $session->id,
            'type' => 'piece_identite',
        ]));
        $response->assertStatus(200);

        // Test Signature download
        $sigResponse = $this->actingAs($admin)->get(route('admin.document.download', [
            'session' => $session->id,
            'type' => 'signature',
        ]));
        $sigResponse->assertStatus(200);

        // Test Verso download
        $versoResponse = $this->actingAs($admin)->get(route('admin.document.download', [
            'session' => $session->id,
            'type' => 'piece_verso',
        ]));
        $versoResponse->assertStatus(200);
    }
}
