<?php

namespace Tests\Feature;

use App\Console\Commands\CheckOnboardingSlaCommand;
use App\Console\Commands\CheckSubscriptionSlaCommand;
use App\Mail\OnboardingSlaAlertMail;
use App\Mail\SubscriptionSlaAlertMail;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SystemSettingAndSlaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_system_settings_get_and_set(): void
    {
        SystemSetting::set('compliance_emails', 'test-conformite@example.test');
        $this->assertSame('test-conformite@example.test', SystemSetting::get('compliance_emails'));

        SystemSetting::set('onboarding_sla_hours', 12, 'integer');
        $this->assertSame(12, SystemSetting::get('onboarding_sla_hours'));
    }

    public function test_onboarding_sla_command_sends_alert_for_overdue_sessions(): void
    {
        SystemSetting::set('compliance_emails', 'alert-conformite@example.test');
        SystemSetting::set('onboarding_sla_hours', 24);

        $client = User::create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'client-sla@example.test',
            'password' => bcrypt('password'),
        ]);

        $session = $client->onboardingSession()->create([
            'status' => 'completed',
            'current_step' => 'completed',
            'payload' => [],
        ]);

        // Simuler un dossier soumis il y a 30 heures
        $session->updated_at = now()->subHours(30);
        $session->save(['timestamps' => false]);

        $this->artisan(CheckOnboardingSlaCommand::class)
            ->assertExitCode(0);

        Mail::assertSent(OnboardingSlaAlertMail::class, function ($mail) {
            return $mail->hasTo('alert-conformite@example.test') && $mail->overdueSessions->count() === 1;
        });
    }

    public function test_subscription_sla_command_sends_alert_for_unreviewed_paid_subscriptions(): void
    {
        SystemSetting::set('compliance_emails', 'conformite-sub@example.test');
        SystemSetting::set('accounting_emails', 'comptabilite-sub@example.test');
        SystemSetting::set('subscription_sla_hours', 48);

        $client = User::create([
            'first_name' => 'Marie',
            'last_name' => 'Curie',
            'email' => 'marie@example.test',
            'password' => bcrypt('password'),
        ]);

        $product = Product::create([
            'libelle' => 'FCP PEK SERENITE',
            'description' => 'Fonds de placement',
            'vl' => 10000,
            'seuil_minimum' => 50000,
            'statut' => 'actif',
        ]);

        $subscription = Subscription::create([
            'user_id' => $client->id,
            'product_id' => $product->id,
            'nb_parts' => 10,
            'prix_unitaire' => 10000,
            'montant_total' => 100000,
            'moyen_paiement' => 'card',
            'statut' => 'Succès',
            'reference_transaction' => 'TX-SLA-001',
        ]);

        // Simuler souscription payée il y a 50 heures sans revue interne
        $subscription->created_at = now()->subHours(50);
        $subscription->save(['timestamps' => false]);

        $this->artisan(CheckSubscriptionSlaCommand::class)
            ->assertExitCode(0);

        Mail::assertSent(SubscriptionSlaAlertMail::class, function ($mail) {
            return $mail->overdueSubscriptions->count() === 1;
        });
    }

    public function test_subscription_internal_reviews_can_be_updated(): void
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Reviewer',
            'email' => 'admin-reviewer@example.test',
            'password' => bcrypt('password'),
        ]);

        $client = User::create([
            'first_name' => 'Paul',
            'last_name' => 'Valery',
            'email' => 'paul@example.test',
            'password' => bcrypt('password'),
        ]);

        $product = Product::create([
            'libelle' => 'FCP PEK SERENITE',
            'description' => 'Fonds de placement',
            'vl' => 10000,
            'seuil_minimum' => 50000,
            'statut' => 'actif',
        ]);

        $subscription = Subscription::create([
            'user_id' => $client->id,
            'product_id' => $product->id,
            'nb_parts' => 5,
            'prix_unitaire' => 10000,
            'montant_total' => 50000,
            'moyen_paiement' => 'card',
            'statut' => 'Succès',
            'reference_transaction' => 'TX-INT-001',
        ]);

        $subscription->update([
            'compliance_reviewed_at' => now(),
            'compliance_reviewed_by_user_id' => $admin->id,
            'accounting_reviewed_at' => now(),
            'accounting_reviewed_by_user_id' => $admin->id,
        ]);

        $fresh = $subscription->fresh();
        $this->assertNotNull($fresh->compliance_reviewed_at);
        $this->assertSame($admin->id, $fresh->compliance_reviewed_by_user_id);
        $this->assertNotNull($fresh->accounting_reviewed_at);
        $this->assertSame($admin->id, $fresh->accounting_reviewed_by_user_id);
    }
}
