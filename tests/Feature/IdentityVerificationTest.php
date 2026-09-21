<?php

namespace Tests\Feature;

use App\Models\IdentityVerification;
use App\Models\OnboardingSession;
use App\Models\User;
use App\Services\IdentityVerification\IdenfyIdentityVerificationProvider;
use App\Services\IdentityVerification\IdentityVerificationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class IdentityVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_idenfy_hmac_verifier_accepts_only_the_raw_body_signature(): void
    {
        config(['services.idenfy.webhook_signing_secret' => 'webhook-secret']);
        $provider = new IdenfyIdentityVerificationProvider;
        $raw = '{"scanRef":"scan-001","final":true}';
        $signature = hash_hmac('sha256', $raw, 'webhook-secret');

        $this->assertTrue($provider->verifyWebhookSignature($raw, $signature));
        $this->assertFalse($provider->verifyWebhookSignature($raw.' ', $signature));
        $this->assertFalse($provider->verifyWebhookSignature($raw, 'invalid'));
    }

    public function test_real_idenfy_session_request_uses_the_current_v2_contract(): void
    {
        config([
            'app.url' => 'https://api.example.test',
            'app.frontend_url' => 'https://app.example.test',
            'identity_verification.session_lifetime' => 900,
            'identity_verification.session_length' => 600,
            'services.idenfy.base_url' => 'https://ivs.idenfy.com',
            'services.idenfy.api_key' => 'server-key',
            'services.idenfy.api_secret' => 'server-secret',
            'services.idenfy.callback_url' => 'https://api.example.test/api/identity-verification/idenfy/webhook',
            'services.idenfy.country' => 'CM',
            'services.idenfy.documents' => ['PASSPORT', 'ID_CARD'],
            'services.idenfy.allowed_redirect_hosts' => ['ivs.idenfy.com'],
        ]);
        [, $session] = $this->userWithKyc();
        $verification = IdentityVerification::create([
            'onboarding_session_id' => $session->id,
            'provider' => 'idenfy',
            'identity_data_hash' => IdentityVerification::fingerprint($session->payload),
            'status' => 'initiated',
        ]);
        Http::fake([
            'https://ivs.idenfy.com/api/v2/token' => Http::response([
                'authToken' => 'short-lived-client-token',
                'scanRef' => 'scan-reference-v2',
                'redirectUrl' => 'https://ivs.idenfy.com/api/v2/redirect?authToken=short-lived-client-token',
            ], 201),
        ]);

        $result = (new IdenfyIdentityVerificationProvider)->createSession($verification, $session);

        $this->assertSame('scan-reference-v2', $result['provider_reference']);
        $this->assertSame(
            'https://ivs.idenfy.com/api/v2/redirect?authToken=short-lived-client-token',
            $result['session_url']
        );
        Http::assertSent(function ($request) use ($verification) {
            return $request->url() === 'https://ivs.idenfy.com/api/v2/token'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('server-key:server-secret'))
                && $request['clientId'] === $verification->client_reference
                && $request['externalRef'] === $verification->id
                && $request['tokenType'] === 'IDENTIFICATION'
                && $request['expiryTime'] === 900
                && $request['sessionLength'] === 600
                && $request['firstName'] === 'Ada'
                && $request['lastName'] === 'Lovelace'
                && $request['country'] === 'CM'
                && $request['documents'] === ['PASSPORT', 'ID_CARD']
                && $request['successUrl'] === 'https://app.example.test/onboarding?identity=returned'
                && $request['callbackUrl'] === 'https://api.example.test/api/identity-verification/idenfy/webhook'
                && $request['checkLiveness'] === true
                && $request['reviewSuccessful'] === true
                && $request['reviewFailed'] === true
                && ! isset($request['client'], $request['urls'], $request['settings']);
        });
    }

    public function test_identity_session_is_created_server_side_without_exposing_provider_references(): void
    {
        config([
            'identity_verification.required' => true,
            'identity_verification.provider' => 'idenfy',
            'services.idenfy.api_key' => 'server-key',
            'services.idenfy.api_secret' => 'server-secret',
            'services.idenfy.webhook_signing_secret' => 'webhook-secret',
        ]);

        [$user, $session] = $this->userWithKyc();
        $provider = Mockery::mock(IdentityVerificationProvider::class);
        $provider->shouldReceive('createSession')
            ->once()
            ->andReturnUsing(function (IdentityVerification $verification) {
                return [
                    'provider_reference' => 'provider-scan-reference',
                    'session_url' => 'https://ui.idenfy.com/session/short-lived-token',
                    'expires_at' => Carbon::now()->addMinutes(30),
                ];
            });
        $this->app->instance(IdentityVerificationProvider::class, $provider);

        $response = $this->actingAs($user)->postJson('/api/identity-verification/session');

        $response->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('session_url', 'https://ui.idenfy.com/session/short-lived-token')
            ->assertJsonMissingPath('verification.provider_reference')
            ->assertJsonMissingPath('verification.client_reference');

        $this->assertDatabaseHas('identity_verifications', [
            'onboarding_session_id' => $session->id,
            'provider_reference' => 'provider-scan-reference',
            'status' => 'pending',
        ]);
    }

    public function test_signed_webhook_is_idempotent_and_stores_no_provider_pii(): void
    {
        [$user, $session] = $this->userWithKyc();
        $verification = IdentityVerification::create([
            'onboarding_session_id' => $session->id,
            'provider' => 'idenfy',
            'client_reference' => '019c91f4-6345-7b13-80dd-6b8556bcd276',
            'provider_reference' => 'scan-reference-001',
            'identity_data_hash' => IdentityVerification::fingerprint($session->payload),
            'status' => 'pending',
        ]);

        $provider = Mockery::mock(IdentityVerificationProvider::class);
        $provider->shouldReceive('verifyWebhookSignature')->twice()->andReturnTrue();
        $this->app->instance(IdentityVerificationProvider::class, $provider);

        $raw = json_encode([
            'scanRef' => 'scan-reference-001',
            'clientId' => $verification->client_reference,
            'status' => [
                'overall' => 'APPROVED',
                'autoDocument' => 'DOC_VALIDATED',
                'autoFace' => 'FACE_MATCH',
            ],
            'final' => true,
            'data' => [
                'name' => 'DO NOT STORE THIS NAME',
                'documentImage' => 'base64-document-data',
            ],
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_IDENFY_SIGNATURE' => str_repeat('a', 64),
            'HTTP_IDENFY_EVENT_TYPE' => 'IDENTIFICATION',
        ];

        $this->call('POST', '/api/identity-verification/idenfy/webhook', [], [], [], $headers, $raw)
            ->assertOk()
            ->assertJsonPath('status', 'received');
        $this->call('POST', '/api/identity-verification/idenfy/webhook', [], [], [], $headers, $raw)
            ->assertOk()
            ->assertJsonPath('status', 'received');

        $verification->refresh();
        $this->assertTrue($verification->is_final);
        $this->assertSame('approved', $verification->status);
        $this->assertTrue($verification->document_validated);
        $this->assertTrue($verification->face_matched);
        $this->assertDatabaseCount('identity_verification_events', 1);
        $this->assertNotNull($session->fresh()->identity_verified_at);

        $storedEvent = json_encode(
            \DB::table('identity_verification_events')->first(),
            JSON_THROW_ON_ERROR
        );
        $this->assertStringNotContainsString('DO NOT STORE THIS NAME', $storedEvent);
        $this->assertStringNotContainsString('base64-document-data', $storedEvent);
    }

    public function test_invalid_identity_webhook_signature_is_rejected_before_processing(): void
    {
        $provider = Mockery::mock(IdentityVerificationProvider::class);
        $provider->shouldReceive('verifyWebhookSignature')->once()->andReturnFalse();
        $this->app->instance(IdentityVerificationProvider::class, $provider);

        $this->call(
            'POST',
            '/api/identity-verification/idenfy/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_IDENFY_SIGNATURE' => 'invalid'],
            '{"scanRef":"untrusted"}'
        )->assertForbidden();

        $this->assertDatabaseCount('identity_verification_events', 0);
    }

    /**
     * @return array{User, OnboardingSession}
     */
    private function userWithKyc(): array
    {
        $user = User::create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'password' => bcrypt('a-secure-password'),
        ]);
        $session = $user->onboardingSession()->create([
            'status' => 'in_progress',
            'current_step' => 'identity',
            'payload' => $this->kycPayload(),
        ]);

        return [$user, $session];
    }

    private function kycPayload(): array
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
        ];
    }
}
