<?php

namespace Tests\Unit;

use App\Models\OnboardingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSignatureCityTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_city_from_explicit_payload(): void
    {
        $user = User::factory()->create([
            'city' => 'Yaoundé',
        ]);

        $session = new OnboardingSession();
        $session->user_id = $user->id;
        $session->setRelation('user', $user);
        $session->payload = [
            'ville' => 'Bafoussam',
            'adresse' => 'Quartier Djeleng, Bafoussam',
        ];

        $this->assertEquals('Bafoussam', $session->getClientCity());
    }

    public function test_resolves_city_from_user_profile_when_missing_in_payload(): void
    {
        $user = User::factory()->create([
            'city' => 'Douala',
        ]);

        $session = new OnboardingSession();
        $session->user_id = $user->id;
        $session->setRelation('user', $user);
        $session->payload = [
            'nom' => 'Test',
        ];

        $this->assertEquals('Douala', $session->getClientCity());
        $this->assertEquals('Douala', $user->getResidenceCity());
    }

    public function test_resolves_city_extracted_from_address_string(): void
    {
        $user = User::factory()->create([
            'city' => null,
        ]);

        $session = new OnboardingSession();
        $session->user_id = $user->id;
        $session->setRelation('user', $user);
        $session->payload = [
            'adresse' => 'Quartier Bastos, Yaoundé',
        ];

        $this->assertEquals('Yaoundé', $session->getClientCity());
    }

    public function test_resolves_city_when_address_is_single_city_name(): void
    {
        $user = User::factory()->create([
            'city' => null,
        ]);

        $session = new OnboardingSession();
        $session->user_id = $user->id;
        $session->setRelation('user', $user);
        $session->payload = [
            'adresse' => 'Douala',
        ];

        $this->assertEquals('Douala', $session->getClientCity());
    }

    public function test_pdf_views_render_with_resolved_client_city(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'city' => 'Douala',
        ]);

        $session = new OnboardingSession();
        $session->id = 'test-session-uuid';
        $session->user_id = $user->id;
        $session->setRelation('user', $user);
        $session->payload = [
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'civ' => 'M.',
            'nat' => 'Camerounaise',
            'dob' => '1990-01-01',
            'lieu_naiss' => 'Douala',
            'adresse' => 'Douala',
            'tel' => '+237690000000',
            'email' => 'jean.dupont@test.com',
            'piece' => 'CNI',
            'num_piece' => '123456789',
            'expiration_piece' => '2030-01-01',
            'categorie_client' => 'Particulier',
        ];

        $pdfData = [
            'session' => $session,
            'payload' => $session->payload,
            'user' => $user,
            'signature' => null,
            'logo' => '',
            'client_city' => $session->getClientCity(),
        ];

        $kycHtml = view('pdfs.onboarding.fiche_kyc', $pdfData)->render();
        $riskHtml = view('pdfs.onboarding.profil_investisseur', $pdfData)->render();
        $labftHtml = view('pdfs.onboarding.questionnaire_labft', $pdfData)->render();

        $this->assertStringContainsString('Fait à Douala, le', $kycHtml);
        $this->assertStringNotContainsString('Fait à Yaoundé, le', $kycHtml);

        $this->assertStringContainsString('Fait à Douala, le', $riskHtml);
        $this->assertStringNotContainsString('Fait à Yaoundé, le', $riskHtml);

        $this->assertStringContainsString('Fait à Douala, le', $labftHtml);
        $this->assertStringNotContainsString('Fait à Yaoundé, le', $labftHtml);
    }
}
