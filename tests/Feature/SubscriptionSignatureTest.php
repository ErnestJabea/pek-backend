<?php

namespace Tests\Feature;

use App\Models\BankDetail;
use App\Models\OnboardingSession;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionBulletinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubscriptionSignatureTest extends TestCase
{
    use RefreshDatabase;

    private function generateBase64Png(): string
    {
        // 1x1 transparent PNG in base64
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    }

    public function test_user_has_signature_attribute(): void
    {
        Storage::fake('kyc_private');
        $user = User::factory()->create();

        $this->assertFalse($user->fresh()->has_signature);

        $session = OnboardingSession::create([
            'user_id' => $user->id,
            'status' => 'completed',
            'current_step' => 'completed',
            'signature_path' => 'signatures/sig_test.png',
        ]);
        Storage::disk('kyc_private')->put('signatures/sig_test.png', 'fake-image');

        $this->assertTrue($user->fresh()->has_signature);
    }

    public function test_subscription_creation_with_signature_and_save_for_future_true(): void
    {
        Storage::fake('kyc_private');
        $user = User::factory()->create(['categorie_client' => 'Particulier']);
        $product = Product::create([
            'libelle' => 'FCP Kori Sérénité',
            'vl' => 10000,
            'seuil_minimum' => 50000,
            'is_active' => true,
        ]);
        BankDetail::create([
            'bank_name' => 'AFRILAND FIRST BANK',
            'iban' => 'CM211000500001',
            'rib' => '100050000123',
            'is_active' => true,
        ]);

        $base64Sig = $this->generateBase64Png();

        $response = $this->actingAs($user)->postJson('/api/subscriptions', [
            'product_id' => $product->id,
            'investment_amount' => 50000,
            'moyen_paiement' => 'bank_transfer',
            'signature' => $base64Sig,
            'save_for_future' => true,
        ], [
            'Idempotency-Key' => 'test-idemp-sig-1',
        ]);

        $response->assertStatus(201);
        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertNotNull($subscription);
        $this->assertNotNull($subscription->signature_path);
        Storage::disk('kyc_private')->assertExists($subscription->signature_path);

        $freshUser = $user->fresh();
        $this->assertTrue($freshUser->has_signature);
        $this->assertNotNull($freshUser->onboardingSession?->signature_path);
        Storage::disk('kyc_private')->assertExists($freshUser->onboardingSession->signature_path);

        // Test SubscriptionBulletinService picks up the signature
        $bulletinService = app(SubscriptionBulletinService::class);
        $bulletinData = $bulletinService->getBulletinData($subscription);
        $this->assertNotNull($bulletinData['signature_client']);
        $this->assertStringStartsWith('data:image/png;base64,', $bulletinData['signature_client']);
    }

    public function test_subscription_creation_with_signature_and_save_for_future_false(): void
    {
        Storage::fake('kyc_private');
        $user = User::factory()->create(['categorie_client' => 'Particulier']);
        $product = Product::create([
            'libelle' => 'FCP Kori Sérénité',
            'vl' => 10000,
            'seuil_minimum' => 50000,
            'is_active' => true,
        ]);
        BankDetail::create([
            'bank_name' => 'AFRILAND FIRST BANK',
            'iban' => 'CM211000500001',
            'rib' => '100050000123',
            'is_active' => true,
        ]);

        $base64Sig = $this->generateBase64Png();

        $response = $this->actingAs($user)->postJson('/api/subscriptions', [
            'product_id' => $product->id,
            'investment_amount' => 50000,
            'moyen_paiement' => 'bank_transfer',
            'signature' => $base64Sig,
            'save_for_future' => false,
        ], [
            'Idempotency-Key' => 'test-idemp-sig-2',
        ]);

        $response->assertStatus(201);
        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertNotNull($subscription);
        $this->assertNotNull($subscription->signature_path);
        Storage::disk('kyc_private')->assertExists($subscription->signature_path);

        // User profile signature must NOT be set
        $this->assertFalse($user->fresh()->has_signature);

        // Bulletin for this specific subscription must STILL have the signature
        $bulletinService = app(SubscriptionBulletinService::class);
        $bulletinData = $bulletinService->getBulletinData($subscription);
        $this->assertNotNull($bulletinData['signature_client']);
        $this->assertStringStartsWith('data:image/png;base64,', $bulletinData['signature_client']);
    }

    public function test_user_signature_endpoint(): void
    {
        Storage::fake('kyc_private');
        $user = User::factory()->create();

        $base64Sig = $this->generateBase64Png();

        $response = $this->actingAs($user)->postJson('/api/user/signature', [
            'signature' => $base64Sig,
        ]);

        $response->assertOk();
        $response->assertJson(['has_signature' => true]);

        $freshUser = $user->fresh();
        $this->assertTrue($freshUser->has_signature);
        $this->assertNotNull($freshUser->onboardingSession?->signature_path);
        Storage::disk('kyc_private')->assertExists($freshUser->onboardingSession->signature_path);
    }
}
