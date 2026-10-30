<?php

namespace Tests\Feature;

use App\Filament\Resources\OnboardingSessionResource\Pages\CreateOnboardingSession;
use App\Filament\Resources\OnboardingSessionResource\Pages\ViewOnboardingSession;
use App\Models\AdminDepartment;
use App\Models\OnboardingSession;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhysicalLegacyOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['identity_verification.required' => false]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Mail::fake();
        Storage::fake('kyc_private');
    }

    public function test_can_upload_and_download_physical_legacy_pdfs(): void
    {
        // 1. Setup Admin Maker (Operations) and Checker (Compliance)
        $opsDept = AdminDepartment::firstOrCreate(
            ['name' => 'Opérations & Tenue de Comptes'],
            ['permissions' => ['view_onboarding::session', 'update_onboarding::session', 'create_onboarding::session']]
        );
        $compDept = AdminDepartment::firstOrCreate(
            ['name' => 'Conformité / KYC'],
            ['permissions' => ['view_onboarding::session', 'update_onboarding::session']]
        );

        $maker = User::factory()->create([
            'email' => 'agent.ops@koriassetmanagement.com',
            'role' => 'admin',
            'admin_department_id' => $opsDept->id,
        ]);
        $maker->assignRole(Role::findOrCreate('admin', 'web'));

        $checker = User::factory()->create([
            'email' => 'officer.compliance@koriassetmanagement.com',
            'role' => 'super_admin',
            'admin_department_id' => $compDept->id,
        ]);
        $checker->assignRole(Role::findOrCreate('super_admin', 'web'));

        // 2. Client historique
        $client = User::factory()->create([
            'email' => 'client.historique@example.com',
            'first_name' => 'Amadou',
            'last_name' => 'Ahidjo',
            'role' => 'client',
        ]);

        // 3. Fichiers PDF scannés simulés
        $cniFile = UploadedFile::fake()->create('cni_recto.pdf', 300, 'application/pdf');
        $riskFile = UploadedFile::fake()->create('profil_investisseur_signe.pdf', 500, 'application/pdf');
        $labftFile = UploadedFile::fake()->create('questionnaire_labft_signe.pdf', 400, 'application/pdf');
                $kycFile = UploadedFile::fake()->create('fiche_kyc_signee.pdf', 600, 'application/pdf');

        $cniPath = $cniFile->store('secure_onboardings/documents', 'kyc_private');
        $riskPath = $riskFile->store('secure_onboardings/documents', 'kyc_private');
        $labftPath = $labftFile->store('secure_onboardings/documents', 'kyc_private');
                $kycPath = $kycFile->store('secure_onboardings/documents', 'kyc_private');

        // 4. Création du dossier d'onboarding par l'agent Backoffice
        $session = OnboardingSession::create([
            'user_id' => $client->id,
            'current_step' => 'completed',
            'status' => 'completed',
            'doc_piece_identite' => $cniPath,
            'payload' => [
                'nom' => 'Ahidjo',
                'prenom' => 'Amadou',
                'email' => 'client.historique@example.com',
                'is_dossier_physique' => true,
                'regularisation_backoffice' => true,
                'risk_profile' => 'Modéré',
                'risk_score' => 15,
                'doc_piece_identite' => $cniPath,
                'doc_profil_investisseur' => $riskPath,
                'doc_labft' => $labftPath,
                'doc_kyc_physique' => $kycPath,
                'created_by_user_id' => $maker->id,
            ],
        ]);

        // 5. Test téléchargement des pièces physiques via admin.document.download
        $resRiskDoc = $this->actingAs($checker)->get(route('admin.document.download', [
            'session' => $session->id,
            'type' => 'profil_investisseur',
        ]));
        $resRiskDoc->assertOk();
        $this->assertStringContainsString('profil_investisseur_', $resRiskDoc->headers->get('content-disposition'));

        $resLabftDoc = $this->actingAs($checker)->get(route('admin.document.download', [
            'session' => $session->id,
            'type' => 'labft',
        ]));
        $resLabftDoc->assertOk();

        $resKycDoc = $this->actingAs($checker)->get(route('admin.document.download', [
            'session' => $session->id,
            'type' => 'kyc_physique',
        ]));
        $resKycDoc->assertOk();

        // 6. Test téléchargement via route standard admin.pdf.download (sert le PDF physique en priorité)
        $resRiskPdf = $this->actingAs($checker)->get(route('admin.pdf.download', [
            'session' => $session->id,
            'type' => 'risk',
        ]));
        $resRiskPdf->assertOk();

        $resLabftPdf = $this->actingAs($checker)->get(route('admin.pdf.download', [
            'session' => $session->id,
            'type' => 'labft',
        ]));
        $resLabftPdf->assertOk();

        $resKycPdf = $this->actingAs($checker)->get(route('admin.pdf.download', [
            'session' => $session->id,
            'type' => 'kyc',
        ]));
        $resKycPdf->assertOk();

        // 7. Validation finale par la Conformité (sans notifications par défaut)
        Livewire::actingAs($checker)
            ->test(ViewOnboardingSession::class, ['record' => $session->getRouteKey()])
            ->callAction('validate', [
                'send_notification_email' => false,
                'create_in_app_notification' => false,
            ])
            ->assertHasNoActionErrors();

        $session->refresh();
        $client->refresh();

        $this->assertEquals('validated', $session->status);
        $this->assertEquals('validated', $client->onboarding_status);
        $this->assertTrue($client->onboarding_completed);

        // Aucun e-mail intempestif envoyé au client
        Mail::assertNothingSent();
    }
}
