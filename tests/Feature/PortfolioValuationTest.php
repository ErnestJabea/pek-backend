<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVl;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PortfolioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioValuationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Product $productA;

    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'password' => bcrypt('password123456'),
        ]);

        $this->user->onboardingSession()->create([
            'status' => 'validated',
            'current_step' => 'completed',
            'payload' => [],
        ]);

        $this->productA = Product::create([
            'libelle' => 'FCP Kori Croissance',
            'description' => 'Fonds d’actions dynamiques',
            'vl' => 10000.00,
            'seuil_minimum' => 50000.00,
            'is_active' => true,
        ]);

        $this->productB = Product::create([
            'libelle' => 'FCP Kori Sérénité',
            'description' => 'Fonds obligataire prudent',
            'vl' => 5000.00,
            'seuil_minimum' => 20000.00,
            'is_active' => true,
        ]);
    }

    public function test_portfolio_valuation_returns_zero_when_no_active_subscriptions(): void
    {
        $portfolioService = new PortfolioService;
        $valuation = $portfolioService->getClientValuation($this->user->id);

        $this->assertEquals(0.0, $valuation['valorisation_totale']);
        $this->assertEquals(0.0, $valuation['cout_revient_total']);
        $this->assertEquals(0.0, $valuation['plus_value_totale']);
        $this->assertEquals(0.0, $valuation['rendement_global']);
        $this->assertEquals(0, $valuation['nb_positions']);
        $this->assertEquals(0, $valuation['nb_produits']);
        $this->assertEmpty($valuation['positions']);
        $this->assertEmpty($valuation['product_positions']);
    }

    public function test_portfolio_valuation_calculates_single_subscription_with_vl_update(): void
    {
        // 1. Client souscrit à 10 parts de Product A (prix = 10 000, total = 101 000 avec frais)
        Subscription::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productA->id,
            'nb_parts' => 10,
            'prix_unitaire' => 10000.00,
            'montant_total' => 101000.00,
            'moyen_paiement' => 'card',
            'statut' => 'Succès',
            'reference_transaction' => 'FCP-TEST-001',
            'idempotency_key' => 'key-001',
        ]);

        // 2. Publication d'une nouvelle VL de 12 000 FCFA pour Product A
        ProductVl::create([
            'product_id' => $this->productA->id,
            'vl' => 12000.00,
            'date_vl' => now()->subDay(),
        ]);

        $portfolioService = new PortfolioService;
        $valuation = $portfolioService->getClientValuation($this->user->id);

        // Valorisation = 10 parts * 12 000 = 120 000 FCFA
        $this->assertEquals(120000.00, $valuation['valorisation_totale']);
        $this->assertEquals(100000.00, $valuation['cout_revient_total']);
        $this->assertEquals(20000.00, $valuation['plus_value_totale']);
        $this->assertEquals(20.0, $valuation['rendement_global']);
        $this->assertEquals(1, $valuation['nb_positions']);
        $this->assertEquals(1, $valuation['nb_produits']);
    }

    public function test_portfolio_valuation_accumulates_multiple_subscriptions_for_same_and_different_products(): void
    {
        // Subscription 1: Product A (10 parts at 10 000, Total = 101 000)
        Subscription::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productA->id,
            'nb_parts' => 10,
            'prix_unitaire' => 10000.00,
            'montant_total' => 101000.00,
            'moyen_paiement' => 'card',
            'statut' => 'Succès',
            'reference_transaction' => 'FCP-TEST-001',
            'idempotency_key' => 'key-001',
        ]);

        // Subscription 2: Product A again (5 parts at 11 000, Total = 55 550)
        Subscription::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productA->id,
            'nb_parts' => 5,
            'prix_unitaire' => 11000.00,
            'montant_total' => 55550.00,
            'moyen_paiement' => 'mobile_money',
            'statut' => 'Succès',
            'reference_transaction' => 'FCP-TEST-002',
            'idempotency_key' => 'key-002',
        ]);

        // Subscription 3: Product B (20 parts at 5 000, Total = 101 000)
        Subscription::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productB->id,
            'nb_parts' => 20,
            'prix_unitaire' => 5000.00,
            'montant_total' => 101000.00,
            'moyen_paiement' => 'bank_transfer',
            'statut' => 'Succès',
            'reference_transaction' => 'FCP-TEST-003',
            'idempotency_key' => 'key-003',
        ]);

        // Publish latest VLs
        ProductVl::create([
            'product_id' => $this->productA->id,
            'vl' => 12500.00,
            'date_vl' => now(),
        ]);

        ProductVl::create([
            'product_id' => $this->productB->id,
            'vl' => 5200.00,
            'date_vl' => now(),
        ]);

        $portfolioService = new PortfolioService;
        $valuation = $portfolioService->getClientValuation($this->user->id);

        // Product A summary:
        // Total parts = 15
        // Valuation = 15 * 12 500 = 187 500 FCFA

        // Product B summary:
        // Total parts = 20
        // Valuation = 20 * 5 200 = 104 000 FCFA

        // Global Cumulative Total:
        // Total Valuation = 187 500 + 104 000 = 291 500 FCFA

        $this->assertEquals(291500.00, $valuation['valorisation_totale']);
        $this->assertEquals(255000.00, $valuation['cout_revient_total']);
        $this->assertEquals(36500.00, $valuation['plus_value_totale']);
        $this->assertEquals(14.3137, $valuation['rendement_global']);
        $this->assertEquals(3, $valuation['nb_positions']);
        $this->assertEquals(2, $valuation['nb_produits']);

        // Test API Endpoint GET /api/portfolio/valuation
        $response = $this->actingAs($this->user)->getJson('/api/portfolio/valuation');
        $response->assertOk()
            ->assertJsonPath('valorisation_totale', 291500)
            ->assertJsonPath('cout_revient_total', 255000)
            ->assertJsonPath('plus_value_totale', 36500)
            ->assertJsonPath('nb_produits', 2);
    }

    public function test_entry_fees_do_not_reduce_performance_at_unchanged_nav(): void
    {
        $sub = new Subscription;
        $sub->forceFill([
            'user_id' => $this->user->id, 'product_id' => $this->productA->id,
            'nb_parts' => '21.79884030', 'prix_unitaire' => '11468.5000',
            'investment_amount' => 250000, 'subscription_fee' => 2500,
            'montant_total' => 252500, 'moyen_paiement' => 'mtn_momo',
            'statut' => 'Succès', 'reference_transaction' => 'FCP-NET-250K',
        ])->saveQuietly();
        $nav = ProductVl::create(['product_id' => $this->productA->id, 'vl' => 11468.5, 'date_vl' => now()]);
        $valuation = app(PortfolioService::class)->getClientValuation($this->user->id);
        $this->assertEquals(250000, $valuation['cout_revient_total']);
        $this->assertEquals(250000, $valuation['valorisation_totale']);
        $this->assertEquals(0, $valuation['plus_value_totale']);
        $this->assertEquals(0, $valuation['rendement_global']);
        $this->assertEquals(0, $valuation['positions'][0]['plus_value']);
        $this->assertEquals(0, $valuation['product_positions'][0]['plus_value']);
        $this->assertEquals(11468.5, $valuation['product_positions'][0]['pmp']);
        $this->assertSame('252500.00', $sub->fresh()->montant_total);
        $this->assertSame(2500, $sub->fresh()->subscription_fee);
        $this->actingAs($this->user)->getJson('/api/portfolio/valuation')->assertOk()
            ->assertJsonPath('plus_value_totale', 0)->assertJsonPath('cout_revient_total', 250000);
        $nav->update(['vl' => 12000]);
        $valuation = app(PortfolioService::class)->getClientValuation($this->user->id);
        $this->assertEquals(11586.08, $valuation['plus_value_totale']);
        $nav->update(['vl' => 11000]);
        $valuation = app(PortfolioService::class)->getClientValuation($this->user->id);
        $this->assertEquals(-10212.76, $valuation['plus_value_totale']);
    }
}
