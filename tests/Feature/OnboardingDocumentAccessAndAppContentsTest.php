<?php

namespace Tests\Feature;

use App\Models\AppContent;
use App\Models\OnboardingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnboardingDocumentAccessAndAppContentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_contents_endpoint_accessible_via_v1_and_legacy()
    {
        AppContent::create([
            'section' => 'home',
            'key' => 'welcome_hero',
            'title_fr' => 'Bienvenue',
            'body_fr' => 'Investissez avec succès',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $resV1 = $this->getJson('/api/v1/app-contents');
        $resV1->assertStatus(200);
        $resV1->assertJsonPath('welcome_hero.title_fr', 'Bienvenue');

        $resLegacy = $this->getJson('/api/app-contents');
        $resLegacy->assertStatus(200);
        $resLegacy->assertJsonPath('welcome_hero.title_fr', 'Bienvenue');
    }

    public function test_authenticated_user_can_access_their_onboarding_document()
    {
        Storage::fake('kyc_private');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $fakeImagePath = 'secure_onboardings/documents/test-cni.jpg';
        Storage::disk('kyc_private')->put($fakeImagePath, 'fake-jpeg-content');

        $session = OnboardingSession::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'current_step' => 'kyc',
            'status' => 'in_progress',
            'doc_piece_identite' => $fakeImagePath,
            'payload' => [
                'piece_recto' => $fakeImagePath,
            ],
        ]);

        $response = $this->get('/api/v1/onboarding/document/piece_recto');
        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'inline; filename="test-cni.jpg"');
    }

    public function test_user_cannot_access_another_users_document()
    {
        Storage::fake('kyc_private');
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $fakeImagePath = 'secure_onboardings/documents/userA-cni.jpg';
        Storage::disk('kyc_private')->put($fakeImagePath, 'fake-jpeg-content');

        OnboardingSession::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $userA->id,
            'current_step' => 'kyc',
            'status' => 'in_progress',
            'doc_piece_identite' => $fakeImagePath,
            'payload' => ['piece_recto' => $fakeImagePath],
        ]);

        // User B has no session or their own session
        Sanctum::actingAs($userB);
        $response = $this->get('/api/v1/onboarding/document/piece_recto');
        // User B has no onboarding session with this document, so 404
        $response->assertStatus(404);
    }
}
