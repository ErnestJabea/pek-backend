<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ClientStatementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonthlyStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_business_day_calculation_excluding_weekends_and_holidays(): void
    {
        $service = new ClientStatementService();

        // 1. Octobre 2026 : Le 01/11/2026 est un Dimanche + Toussaint -> 1er jour ouvrable doit être le Lundi 02/11/2026
        $firstBizDayNov = $service->getFirstBusinessDayOfNextMonth(2026, 10);
        $this->assertEquals('2026-11-02', $firstBizDayNov->toDateString());
        $this->assertEquals(Carbon::MONDAY, $firstBizDayNov->dayOfWeek);

        // 2. Décembre 2026 : Le 01/01/2027 est Jour de l'An (Vendredi) -> Samedi 02/01, Dimanche 03/01 -> Lundi 04/01/2027
        $firstBizDayJan = $service->getFirstBusinessDayOfNextMonth(2026, 12);
        $this->assertEquals('2027-01-04', $firstBizDayJan->toDateString());
        $this->assertEquals(Carbon::MONDAY, $firstBizDayJan->dayOfWeek);

        // 3. Septembre 2026 : Le 01/10/2026 est un Jeudi normal -> Jeudi 01/10/2026
        $firstBizDayOct = $service->getFirstBusinessDayOfNextMonth(2026, 9);
        $this->assertEquals('2026-10-01', $firstBizDayOct->toDateString());
        $this->assertEquals(Carbon::THURSDAY, $firstBizDayOct->dayOfWeek);
    }

    public function test_user_monthly_statements_api_returns_list_with_availability_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00'));

        $user = User::factory()->create([
            'created_at' => Carbon::parse('2026-08-10'),
        ]);

        Sanctum::actingAs($user);

        SystemSetting::set('monthly_statement_pwa_enabled', true, 'bool');

        $response = $this->getJson('/api/v1/user/monthly-statements');

        $response->assertStatus(200);
        $response->assertJson([
            'enabled' => true,
        ]);

        $data = $response->json();
        $this->assertArrayHasKey('months', $data);
        $months = $data['months'];

        // Au 30 octobre 2026, le mois d'octobre 2026 n'est pas encore disponible
        $october = collect($months)->firstWhere('month', 10);
        $this->assertNotNull($october);
        $this->assertFalse($october['is_available']);
        $this->assertEquals('2026-11-02', $october['first_business_day']);
        $this->assertEquals('closing', $october['status']);

        // Le mois de septembre 2026 est disponible depuis le 01/10/2026
        $september = collect($months)->firstWhere('month', 9);
        $this->assertNotNull($september);
        $this->assertTrue($september['is_available']);
        $this->assertEquals('available', $september['status']);

        Carbon::setTestNow();
    }

    public function test_user_cannot_download_current_month_before_first_business_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00'));

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        SystemSetting::set('monthly_statement_pwa_enabled', true, 'bool');

        // Tentative de téléchargement du relevé d'octobre 2026 le 30 octobre 2026
        $response = $this->getJson('/api/v1/user/monthly-statements/2026/10/download');

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'first_business_day',
        ]);
        $this->assertStringContainsString('pas encore disponible', $response->json('message'));
        $this->assertEquals('2026-11-02', $response->json('first_business_day'));

        Carbon::setTestNow();
    }

    public function test_superadmin_can_disable_monthly_statements_feature(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00'));

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Désactivation de la fonctionnalité dans les SystemSetting par le Superadmin
        SystemSetting::set('monthly_statement_pwa_enabled', false, 'bool');

        // La liste retourne enabled: false
        $response = $this->getJson('/api/v1/user/monthly-statements');
        $response->assertStatus(200);
        $response->assertJson([
            'enabled' => false,
            'months' => [],
        ]);

        // Le téléchargement est rejeté avec HTTP 403
        $dlResponse = $this->getJson('/api/v1/user/monthly-statements/2026/09/download');
        $dlResponse->assertStatus(403);

        Carbon::setTestNow();
    }

    public function test_user_can_download_past_month_statement_pdf(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00'));

        $user = User::factory()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]);
        Sanctum::actingAs($user);

        SystemSetting::set('monthly_statement_pwa_enabled', true, 'bool');

        $response = $this->get('/api/v1/user/monthly-statements/2026/09/download');

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('releve_fcp', (string) $response->headers->get('content-disposition'));

        Carbon::setTestNow();
    }
}
