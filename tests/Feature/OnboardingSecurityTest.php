<?php

namespace Tests\Feature;

use App\Jobs\GenerateOnboardingDocumentsJob;
use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnboardingSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('kyc_private');
    }

    public function test_kyc_payload_is_encrypted_at_rest(): void
    {
        $user = $this->createUser();
        $payload = $this->completePayload();
        $session = $user->onboardingSession()->create([
            'status' => 'in_progress',
            'current_step' => 'kyc',
            'payload' => $payload,
        ]);

        $raw = DB::table('onboarding_sessions')->where('id', $session->id)->first();

        $this->assertNull($raw->payload);
        $this->assertNotEmpty($raw->encrypted_payload);
        $this->assertStringNotContainsString($payload['email'], $raw->encrypted_payload);
        $this->assertStringNotContainsString($payload['num_piece'], $raw->encrypted_payload);
        $this->assertSame($payload['num_piece'], $session->fresh()->payload['num_piece']);
    }

    public function test_submitted_kyc_is_locked_and_has_an_encrypted_snapshot(): void
    {
        config(['identity_verification.required' => false]);
        Queue::fake();
        $user = $this->createUser();
        $session = $user->onboardingSession()->create([
            'status' => 'in_progress',
            'current_step' => 'signature',
            'payload' => $this->completePayload(),
        ]);

        $signature = $this->validPngSignature();
        $this->actingAs($user)
            ->postJson('/api/onboarding/finalize', ['signature' => $signature])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $raw = DB::table('onboarding_sessions')->where('id', $session->id)->first();
        $this->assertSame('completed', $raw->status);
        $this->assertNotNull($raw->submitted_payload);
        $this->assertStringNotContainsString('ada@example.test', $raw->submitted_payload);
        Queue::assertPushed(GenerateOnboardingDocumentsJob::class, function ($job) {
            return ! str_contains(serialize($job), 'iVBORw0KGgo');
        });
        Storage::disk('kyc_private')->assertExists($session->fresh()->signature_path);

        $this->actingAs($user)
            ->postJson('/api/onboarding/save-progress', [
                'step' => 'kyc',
                'payload' => ['adresse' => 'Adresse modifiée'],
            ])
            ->assertStatus(409);
    }

    public function test_identity_approval_must_match_current_identity_data(): void
    {
        config(['identity_verification.required' => true]);
        Queue::fake();
        $user = $this->createUser();
        $payload = $this->completePayload();
        $session = $user->onboardingSession()->create([
            'status' => 'in_progress',
            'current_step' => 'signature',
            'payload' => $payload,
        ]);
        IdentityVerification::create([
            'onboarding_session_id' => $session->id,
            'provider' => 'idenfy',
            'identity_data_hash' => IdentityVerification::fingerprint($payload),
            'status' => 'approved',
            'is_final' => true,
        ]);

        $session->payload = array_replace($payload, ['num_piece' => 'ALTERED-AFTER-CHECK']);
        $session->save();

        $this->actingAs($user)
            ->postJson('/api/onboarding/finalize', [
                'signature' => $this->validPngSignature(),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La vérification stricte de votre identité doit être approuvée avant la signature.');

        $this->assertSame('in_progress', $session->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_arbitrary_fields_cannot_be_added_to_kyc_payload(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson('/api/onboarding/save-progress', [
                'step' => 'kyc',
                'payload' => ['is_admin' => true],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payload');
    }

    private function createUser(): User
    {
        return User::create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'password' => bcrypt('a-secure-password'),
        ]);
    }

    private function completePayload(): array
    {
        return [
            'civ' => 'Mme',
            'nom' => 'Lovelace',
            'prenom' => 'Ada',
            'nat' => 'Camerounaise',
            'dob' => '1985-12-10',
            'lieu_naiss' => 'Yaoundé',
            'adresse' => '1 rue de Test',
            'tel' => '+237600000000',
            'email' => 'ada@example.test',
            'piece' => 'Passeport',
            'num_piece' => 'DOC-SECRET-001',
            'expiration_piece' => now()->addYear()->toDateString(),
            'profession' => 'Ingénieure',
            'employeur' => 'Test SA',
            'situation_mat' => 'Célibataire',
            'pays_residence' => 'Cameroun',
            'secteur' => 'Technologie',
            'revenus_annuels' => '10000000',
            'src_salaire' => true,
            'src_pro_liberal' => false,
            'src_foncier' => false,
            'src_dividendes' => false,
            'src_heritage' => false,
            'src_autre_check' => false,
            'origine_fonds' => 'Salaire',
            'banque' => 'Banque de Test',
            'num_compte' => 'ACCOUNT-SECRET',
            'pays_compte' => 'Cameroun',
            'pays_risque' => 'Non',
            'secteur_sensible' => 'Non',
            'ppe' => 'Non',
            'condamnation' => 'Non',
            'ack_lecture' => true,
            'ack_donnees' => true,
            'tranche_revenus' => '500k_1_5m',
            'epargne_possible' => 'Oui',
            'niveau_risque' => 'moyen',
            'conscience_risque' => 'Oui',
            'objectif_invest' => 'equilibre',
            'horizon_terme' => 'moyen_terme',
            'niveau_perf' => 'moderee',
            'connaissance_marche' => 'moyenne',
            'invest_anterieurs' => 'Non',
        ];
    }

    private function validPngSignature(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }
}
