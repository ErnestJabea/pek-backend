<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_products_are_returned_in_index(): void
    {
        // Clear cache
        \Cache::forget('products_list');

        $activeProduct = Product::create([
            'libelle' => 'Fonds Actif',
            'description' => 'Un fonds actif',
            'vl' => 1000.00,
            'seuil_minimum' => 50000.00,
            'is_active' => true,
        ]);

        $inactiveProduct = Product::create([
            'libelle' => 'Fonds Inactif',
            'description' => 'Un fonds inactif',
            'vl' => 1000.00,
            'seuil_minimum' => 50000.00,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Fonds Actif']);
        $response->assertJsonMissing(['name' => 'Fonds Inactif']);
    }

    public function test_cannot_subscribe_to_inactive_product(): void
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->onboardingSession()->create([
            'status' => 'validated',
            'current_step' => 'completed',
            'payload' => [],
        ]);

        $inactiveProduct = Product::create([
            'libelle' => 'Fonds Inactif',
            'description' => 'Un fonds inactif',
            'vl' => 1000.00,
            'seuil_minimum' => 50000.00,
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'inactive-product-test')
            ->postJson('/api/subscriptions', [
                'product_id' => $inactiveProduct->id,
                'nb_parts' => 10,
                'moyen_paiement' => 'bank_transfer',
                'montant_total' => 50000,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Ce produit n’est pas disponible à la souscription car il est désactivé.');
    }
}
