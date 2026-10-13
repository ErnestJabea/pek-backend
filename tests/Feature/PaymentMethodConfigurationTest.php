<?php

namespace Tests\Feature;

use App\Models\BankDetail;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;
    protected BankDetail $bankDetail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaymentMethodSeeder::class);

        $this->user = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->user->onboardingSession()->create([
            'status' => 'validated',
            'current_step' => 'completed',
            'payload' => [],
        ]);

        $this->product = Product::create([
            'libelle' => 'FCP Kori Sérénité',
            'description' => 'Fonds de test',
            'vl' => 10000.00,
            'seuil_minimum' => 50000.00,
            'is_active' => true,
        ]);

        $this->bankDetail = BankDetail::create([
            'bank_name' => 'Banque de Test',
            'iban' => 'BJ00 0000 0000',
            'rib' => '12345',
            'swift' => 'TESTBJ',
            'is_active' => true,
            'bank_instructions' => 'Virement instructions',
        ]);
    }

    public function test_payment_options_endpoint_returns_dynamic_payment_methods(): void
    {
        $response = $this->getJson('/api/payment-options');

        $response->assertOk()
            ->assertJsonStructure([
                'card',
                'stripe',
                'orange_money',
                'mtn_momo',
                'bank_transfer',
                'methods',
            ]);

        $this->assertTrue($response->json('card'));
        $this->assertTrue($response->json('bank_transfer'));
    }

    public function test_deactivating_card_reflects_in_payment_options_and_blocks_subscription(): void
    {
        PaymentMethod::where('code', 'card')->update(['is_active' => false]);

        $optionsResponse = $this->getJson('/api/payment-options');
        $this->assertFalse($optionsResponse->json('card'));

        $response = $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'test-idemp-card-blocked-'.uniqid())
            ->postJson('/api/subscriptions', [
                'product_id' => $this->product->id,
                'nb_parts' => 5,
                'moyen_paiement' => 'card',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Ce moyen de paiement est actuellement désactivé.',
            ]);
    }

    public function test_deactivating_bank_transfer_blocks_subscription(): void
    {
        PaymentMethod::where('code', 'bank_transfer')->update(['is_active' => false]);

        $optionsResponse = $this->getJson('/api/payment-options');
        $this->assertFalse($optionsResponse->json('bank_transfer'));

        $response = $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'test-idemp-bank-blocked-'.uniqid())
            ->postJson('/api/subscriptions', [
                'product_id' => $this->product->id,
                'nb_parts' => 5,
                'moyen_paiement' => 'bank_transfer',
                'bank_detail_id' => $this->bankDetail->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Ce moyen de paiement est actuellement désactivé.',
            ]);
    }

    public function test_active_bank_transfer_allows_subscription(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'test-idemp-bank-allowed-'.uniqid())
            ->postJson('/api/subscriptions', [
                'product_id' => $this->product->id,
                'nb_parts' => 5,
                'moyen_paiement' => 'bank_transfer',
                'bank_detail_id' => $this->bankDetail->id,
            ]);

        $response->assertStatus(201);
    }
}
