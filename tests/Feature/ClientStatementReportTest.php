<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductVl;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ClientStatementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientStatementReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Client $client;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Kori',
            'email' => 'admin@kori.test',
            'password' => bcrypt('password123456'),
            'role' => 'admin',
        ]);

        $this->client = Client::create([
            'first_name' => 'Amadou',
            'last_name' => 'Bello',
            'email' => 'amadou.bello@example.com',
            'phone' => '+237699001122',
            'city' => 'Douala',
            'country' => 'Cameroun',
            'password' => bcrypt('secret123456'),
            'role' => 'client',
        ]);

        $this->product = Product::create([
            'libelle' => 'FCP KORI SERENITE',
            'code' => 'FCP-KS-001',
            'description' => 'Fonds obligataire',
            'vl' => 10000.00,
            'seuil_minimum' => 50000,
            'is_active' => true,
        ]);
    }

    public function test_statement_service_computes_exact_figures_for_given_period(): void
    {
        // 1. Transaction antérieure au mois (ex: Janvier 2026)
        Subscription::create([
            'user_id' => $this->client->id,
            'product_id' => $this->product->id,
            'reference_transaction' => 'SUB-PREV-001',
            'montant_total' => 204000,
            'subscription_fee' => 4000,
            'investment_amount' => 200000,
            'montant_net' => 200000,
            'prix_unitaire' => 10000,
            'nb_parts' => 20.00,
            'moyen_paiement' => 'Virement bancaire',
            'statut' => 'Succès',
            'value_date' => '2026-01-15',
        ]);

        // 2. Transaction du mois (ex: Février 2026)
        Subscription::create([
            'user_id' => $this->client->id,
            'product_id' => $this->product->id,
            'reference_transaction' => 'SUB-CURR-002',
            'montant_total' => 102000,
            'subscription_fee' => 2000,
            'investment_amount' => 100000,
            'montant_net' => 100000,
            'prix_unitaire' => 10000,
            'nb_parts' => 10.00,
            'moyen_paiement' => 'Virement bancaire',
            'statut' => 'Succès',
            'value_date' => '2026-02-10',
        ]);

        // 3. VL officielle au 28 Février 2026 = 10500.50
        ProductVl::create([
            'product_id' => $this->product->id,
            'date_vl' => '2026-02-28',
            'vl' => 10500.50,
        ]);

        $service = app(ClientStatementService::class);
        $data = $service->getStatementData($this->client, '2026-02-01', '2026-02-28', $this->product->id);

        // Vérifications de base
        $this->assertEquals('Amadou Bello', $data['client']['nom_complet']);
        $this->assertEquals('15/01/2026', $data['client']['first_subscription_date']);
        $this->assertCount(1, $data['fund_statements']);

        $fund = $data['fund_statements'][0];

        // Section I : Synthétique
        // Parts cumulées = 20 + 10 = 30
        $this->assertEquals(30.00, $fund['synthetique']['nb_parts_cumule']);
        // Capital investi = 200 000 + 100 000 = 300 000
        $this->assertEquals(300000, $fund['synthetique']['capital_total_investi']);
        // VL à date = 10500.50
        $this->assertEquals(10500.50, $fund['synthetique']['vl_a_date']);
        // Valorisation = 30 * 10500.50 = 315 015
        $this->assertEquals(315015, $fund['synthetique']['valorisation_portefeuille']);
        // Plus-value = 315 015 - 300 000 = 15 015
        $this->assertEquals(15015, $fund['synthetique']['plus_value']);

        // Section II : Mouvements
        // Parts mois précédent = 20
        $this->assertEquals(20.00, $fund['mouvements']['nb_parts_precedent']);
        // Montant brut investi période = 102 000
        $this->assertEquals(102000, $fund['mouvements']['montant_brut_investi']);
        // Frais de souscription période = 2 000
        $this->assertEquals(2000, $fund['mouvements']['frais_souscription']);
        // Parts mois = 10
        $this->assertEquals(10.00, $fund['mouvements']['nb_parts_mois']);
        // Parts actuelles = 30
        $this->assertEquals(30.00, $fund['mouvements']['nb_parts_actuel']);

        // Section III : Régularisation
        // Total encaissé = 204 000 + 102 000 = 306 000
        $this->assertEquals(306000, $fund['regularisation']['total_encaisse']);
        // Total frais = 4 000 + 2 000 = 6 000
        $this->assertEquals(6000, $fund['regularisation']['total_frais']);
        // Montant total souscriptions = 300 000
        $this->assertEquals(300000, $fund['regularisation']['montant_total_souscriptions']);
        // Montant régularisé = 6 000
        $this->assertEquals(6000, $fund['regularisation']['montant_regularise']);
    }

    public function test_admin_can_preview_statement_html(): void
    {
        $response = $this->actingAs($this->admin)->get(route('client.statement.preview', [
            'client' => $this->client->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Relevé de compte mensuel - FCP');
        $response->assertSee('Amadou Bello');
    }

    public function test_admin_can_download_statement_pdf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('client.statement.pdf', [
            'client' => $this->client->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_unauthorized_user_cannot_access_statement(): void
    {
        $otherClient = Client::create([
            'first_name' => 'Autre',
            'last_name' => 'Client',
            'email' => 'autre@example.com',
            'password' => bcrypt('secret123456'),
            'role' => 'client',
        ]);

        $response = $this->actingAs($otherClient)->get(route('client.statement.preview', [
            'client' => $this->client->id,
        ]));

        $response->assertStatus(403);
    }
}
