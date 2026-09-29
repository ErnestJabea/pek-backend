<?php

namespace Tests\Feature;

use App\Filament\Resources\SubscriptionResource\Pages\EditSubscription;
use App\Filament\Resources\SubscriptionResource\Pages\ListSubscriptions;
use App\Jobs\ProcessS3pCallbacks;
use App\Jobs\ProcessSubscriptionReceipt;
use App\Models\BankDetail;
use App\Models\PaymentProof;
use App\Models\Product;
use App\Models\ProductVl;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\BankPaymentService;
use App\Services\Payments\MobilePaymentService;
use App\Services\Payments\PaymentProofReviewService;
use App\Services\Payments\S3pGateway;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SecurePaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $accountant;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Bus::fake([ProcessS3pCallbacks::class]);
        Http::preventStrayRequests();
        config(['payments.s3p.simulation' => false, 'payments.s3p.allow_staging' => false, 'payments.s3p.credit_test_parts' => false, 'payments.s3p.staging_use_provider_timestamp' => false]);
        $this->travelTo(now()->setDate(2026, 9, 16)->startOfDay()->addHours(12));
        $this->client = User::create(['first_name' => 'Test', 'last_name' => 'Payment', 'email' => bin2hex(random_bytes(8)).'@example.com', 'password' => 'TestingOnly-2026!']);
        $this->client->onboardingSession()->create(['status' => 'validated', 'current_step' => 'completed', 'payload' => []]);
        $this->accountant = User::create(['first_name' => 'Test', 'last_name' => 'Payment', 'email' => bin2hex(random_bytes(8)).'@example.com', 'password' => 'TestingOnly-2026!']);
        $this->accountant->givePermissionTo(Permission::findOrCreate('confirm_bank_payment', 'web'));
        $this->product = Product::create(['libelle' => 'Fonds test', 'vl' => 10000, 'seuil_minimum' => 50000, 'is_active' => true]);
        BankDetail::create(['bank_name' => 'Banque test', 'rib' => 'RIB-TEST-001', 'iban' => 'IBAN-TEST-001', 'is_active' => true]);
    }

    public function test_quarantined_proof_can_be_replaced_but_not_marked_examined(): void
    {
        Storage::fake('payment_private');
        config(['payments.scanner_binary' => null]);
        $sub = $this->bank();
        $this->post('/api/subscriptions/'.$sub->id.'/proofs', [
            'file' => UploadedFile::fake()->image('proof.png'), 'declared_date' => '2026-09-16', 'declared_amount' => 75750,
        ])->assertCreated();
        $proof = PaymentProof::firstOrFail();
        $this->accountant->givePermissionTo(Permission::findOrCreate('review_payment_proof', 'web'));
        $service = app(PaymentProofReviewService::class);
        $service->review($proof, $this->accountant, ['status' => 'replacement_requested', 'note' => 'Document illisible.']);
        $this->assertSame('replacement_requested', $proof->fresh()->review_status);
        $this->assertNull($sub->fresh()->funds_received_at);
        $this->assertDatabaseHas('payment_events', ['subscription_id' => $sub->id, 'type' => 'proof_reviewed']);
        $this->expectException(ValidationException::class);
        $service->review($proof, $this->accountant, ['status' => 'examined', 'note' => 'Examen.']);
    }

    public function test_proof_review_checks_integrity_and_does_not_confirm_funds(): void
    {
        Storage::fake('payment_private');
        config(['payments.scanner_binary' => null]);
        $sub = $this->bank();
        $this->post('/api/subscriptions/'.$sub->id.'/proofs', [
            'file' => UploadedFile::fake()->image('proof.png'), 'declared_date' => '2026-09-16', 'declared_amount' => 75750,
        ])->assertCreated();
        $proof = PaymentProof::firstOrFail();
        $proof->update(['scan_status' => 'clean']);
        $this->accountant->givePermissionTo(Permission::findOrCreate('review_payment_proof', 'web'));
        $service = app(PaymentProofReviewService::class);
        $service->review($proof, $this->accountant, ['status' => 'examined', 'note' => 'Document lisible.']);
        $this->assertSame('examined', $proof->fresh()->review_status);
        $this->assertNull($sub->fresh()->funds_received_at);
        Storage::disk('payment_private')->put($proof->path, 'altered');
        $this->expectException(ValidationException::class);
        $service->review($proof, $this->accountant, ['status' => 'examined', 'note' => 'Examen.']);
    }

    private function backofficeBankPage(Subscription $sub)
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        foreach (['access_admin_panel', 'view_any_subscription', 'view_subscription', 'update_subscription'] as $permission) {
            $this->accountant->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return Livewire::actingAs($this->accountant, 'web')
            ->test(EditSubscription::class, ['record' => $sub->id])->assertSuccessful();
    }

    public function test_backoffice_bank_confirmation_immediately_credits_parts_and_refreshes_form(): void
    {
        $sub = $this->bank();
        ProductVl::create(['product_id' => $this->product->id, 'vl' => 12500, 'date_vl' => '2026-09-15']);
        $this->backofficeBankPage($sub)
            ->callAction('reviewAccounting', data: $this->bankData())
            ->assertHasNoActionErrors()
            ->assertNotified('Fonds confirmés et parts valorisées')
            ->assertSet('data.statut', 'Succès')
            ->assertSet('data.nb_parts', '6.00000000');
        $this->assertSame('Succès', $sub->fresh()->statut);
        $this->assertSame('6.00000000', $sub->fresh()->nb_parts);
        $this->assertSame(1, DB::table('payment_events')->where('subscription_id', $sub->id)->where('type', 'parts_valued')->count());
    }

    public function test_backoffice_bank_retry_finishes_previously_confirmed_payment(): void
    {
        $sub = $this->bank();
        app(BankPaymentService::class)->confirm($sub, $this->accountant, $this->bankData());
        $this->backofficeBankPage($sub->fresh())
            ->callAction('valueBankParts')->assertNotified('Fonds reçus — VL manquante');
        $this->assertSame('En attente', $sub->fresh()->statut);
        ProductVl::create(['product_id' => $this->product->id, 'vl' => 12500, 'date_vl' => '2026-09-15']);
        $this->backofficeBankPage($sub->fresh())
            ->callAction('valueBankParts')->assertNotified('Parts attribuées')
            ->assertSet('data.statut', 'Succès')->assertSet('data.nb_parts', '6.00000000');
        $this->assertSame(1, DB::table('payment_events')->where('subscription_id', $sub->id)->where('type', 'bank_funds_received')->count());
    }

    public function test_bank_parts_cannot_be_credited_before_funds_are_confirmed(): void
    {
        $sub = $this->bank();
        ProductVl::create(['product_id' => $this->product->id, 'vl' => 12500, 'date_vl' => '2026-09-15']);
        try {
            app(BankPaymentService::class)->value($sub);
            $this->fail('Unconfirmed funds were credited');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame('En attente', $sub->fresh()->statut);
            $this->assertNull($sub->fresh()->funds_received_at);
            $this->assertDatabaseMissing('payment_events', ['subscription_id' => $sub->id, 'type' => 'parts_valued']);
        }
    }

    public function test_backoffice_bank_table_confirmation_credits_parts(): void
    {
        $sub = $this->bank();
        ProductVl::create(['product_id' => $this->product->id, 'vl' => 12500, 'date_vl' => '2026-09-15']);
        $this->backofficeBankPage($sub);
        Livewire::actingAs($this->accountant, 'web')
            ->test(ListSubscriptions::class)
            ->assertSuccessful()
            ->callTableAction('reviewAccounting', $sub, data: $this->bankData())
            ->assertHasNoTableActionErrors()->assertNotified('Fonds confirmés et parts valorisées');
        $this->assertSame('Succès', $sub->fresh()->statut);
        $this->assertSame('6.00000000', $sub->fresh()->nb_parts);
    }

    public function test_backoffice_bank_retry_requires_payment_permission(): void
    {
        $sub = $this->bank();
        app(BankPaymentService::class)->confirm($sub, $this->accountant, $this->bankData());
        $this->backofficeBankPage($sub->fresh());
        $this->accountant->revokePermissionTo('confirm_bank_payment');
        ProductVl::create(['product_id' => $this->product->id, 'vl' => 12500, 'date_vl' => '2026-09-15']);
        $this->backofficeBankPage($sub->fresh())->assertActionHidden('valueBankParts');
        $this->assertSame('En attente', $sub->fresh()->statut);
    }

    public function test_s3p_error_contract_for_both_operators_without_new_collect(): void
    {
        foreach (['orange_money', 'mtn_momo'] as $operator) {
            $sub = $this->bank('error-'.$operator);
            $sub->forceFill(['mobile_provider' => $operator, 'mobile_state' => 'errored', 's3p_error_code' => '703107'])->save();
            $this->actingAs($this->client)->getJson('/api/subscriptions/'.$sub->id.'/payment-status')
                ->assertOk()->assertJsonPath('status', 'failed')->assertJsonPath('can_retry', true)
                ->assertJsonPath('payment.error_label', 'Solde insuffisant')
                ->assertJsonPath('payment.redirect_required', false);
            $sub->forceFill(['s3p_error_code' => '999999'])->save();
            $this->getJson('/api/subscriptions/'.$sub->id.'/payment-status')->assertOk()->assertJsonPath('can_retry', false);
            $sub->forceFill(['mobile_state' => 'verification_required'])->save();
            $this->getJson('/api/subscriptions/'.$sub->id.'/payment-status')->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('can_retry', false);
        }
        Http::assertNothingSent();
    }

    public function test_public_s3p_diagnostic_is_disabled_even_with_a_cached_route(): void
    {
        Http::fake();
        foreach (['/api/payments/s3p-direct-test', '/api/v1/payments/s3p-direct-test'] as $uri) {
            $this->postJson($uri, ['service_id' => '20001', 'service_number' => '237699000001'])->assertNotFound();
        }
        try {
            app(\App\Http\Controllers\SubscriptionController::class)->directS3pTest(\Illuminate\Http\Request::create('/'));
            $this->fail('Cached route must be disabled');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        Http::assertNothingSent();
    }

    private function bank(string $key = 'bank-test'): Subscription
    {
        $this->actingAs($this->client)->withHeader('Idempotency-Key', $key)->postJson('/api/subscriptions', [
            'product_id' => $this->product->id, 'investment_amount' => 75000,
            'moyen_paiement' => 'bank_transfer', 'montant_total' => 1, 'subscription_fee' => 0,
        ])->assertCreated()->assertJsonPath('subscription.montant_total', '75750.00');

        return Subscription::where('idempotency_key', $key)->firstOrFail();
    }

    private function bankData(string $date = '2026-09-16', string $reference = 'BANK-001'): array
    {
        return ['received_at' => $date, 'amount' => 75750, 'reference' => $reference];
    }

    public function test_reception_date_prices_parts_not_order_or_review_date(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 10));
        $sub = $this->bank();
        ProductVl::create(['product_id' => $this->product->id, 'vl' => 12500, 'date_vl' => '2026-09-11']);
        ProductVl::create(['product_id' => $this->product->id, 'vl' => 15000, 'date_vl' => '2026-09-15']);
        $this->travelTo(now()->setDate(2026, 9, 16));
        $service = app(BankPaymentService::class);
        $result = $service->confirm($sub, $this->accountant, $this->bankData('2026-09-12'));
        $this->assertSame('Succès', $result->statut);
        $this->assertSame('2026-09-12', $result->value_date->toDateString());
        $this->assertSame('12500.0000', $result->prix_unitaire);
        $this->assertSame('6.00000000', $result->nb_parts);
        $this->assertSame(75000.0, $result->montant_net);
        $this->assertSame(750.0, $result->frais_gestion);
        $service->confirm($sub, $this->accountant, $this->bankData('2026-09-12'));
        Queue::assertPushed(ProcessSubscriptionReceipt::class, 1);
    }

    public function test_missing_nav_never_falls_back_to_current_value_and_reconciles_later(): void
    {
        $sub = $this->bank();
        ProductVl::create(['product_id' => $this->product->id, 'vl' => 11000, 'date_vl' => '2026-09-17']);
        $result = app(BankPaymentService::class)->confirm($sub, $this->accountant, $this->bankData());
        $this->assertSame('En attente', $result->statut);
        $this->assertSame('awaiting_nav', $result->valuation_status);
        Queue::assertNothingPushed();
        ProductVl::create(['product_id' => $this->product->id, 'vl' => 12500, 'date_vl' => '2026-09-15']);
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('6.00000000', $sub->fresh()->nb_parts);
        $this->assertSame('Succès', $sub->fresh()->statut);
    }

    public function test_latest_strictly_prior_nav_is_snapshotted_and_not_repriced(): void
    {
        $sub = $this->bank();
        foreach (['2026-09-10' => 10000, '2026-09-15' => 12500, '2026-09-16' => 15000, '2026-09-17' => 20000] as $date => $vl) {
            ProductVl::create(['product_id' => $this->product->id, 'date_vl' => $date, 'vl' => $vl]);
        }
        $service = app(BankPaymentService::class);
        $result = $service->confirm($sub, $this->accountant, $this->bankData());
        $this->assertSame('6.00000000', $result->nb_parts);
        $this->assertSame('2026-09-16', $result->value_date->toDateString());
        $this->assertSame('2026-09-15', $result->nav_date->toDateString());
        ProductVl::where('product_id', $this->product->id)->whereDate('date_vl', '2026-09-15')->update(['vl' => 25000]);
        $this->assertSame('6.00000000', $service->value($result)->nb_parts);
        Queue::assertPushed(ProcessSubscriptionReceipt::class, 1);
    }

    public function test_unprivileged_user_cannot_confirm_bank_funds(): void
    {
        $sub = $this->bank();
        $this->expectException(HttpException::class);
        app(BankPaymentService::class)->confirm($sub, $this->client, $this->bankData());
    }

    public function test_mismatched_amount_never_marks_funds_received(): void
    {
        $sub = $this->bank();
        try {
            app(BankPaymentService::class)->confirm($sub, $this->accountant, [...$this->bankData(), 'amount' => 75000]);
            $this->fail('A mismatched amount was accepted');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertNull($sub->fresh()->funds_received_at);
        }
    }

    public function test_one_bank_operation_cannot_fund_two_subscriptions(): void
    {
        $one = $this->bank('one');
        $two = $this->bank('two');
        app(BankPaymentService::class)->confirm($one, $this->accountant, $this->bankData());
        try {
            app(BankPaymentService::class)->confirm($two, $this->accountant, $this->bankData());
            $this->fail('Duplicate bank operation accepted');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertNull($two->fresh()->funds_received_at);
        }
    }

    public function test_future_and_preorder_dates_are_rejected(): void
    {
        $sub = $this->bank();
        try {
            app(BankPaymentService::class)->confirm($sub, $this->accountant, $this->bankData('2026-09-17'));
            $this->fail('Future date accepted');
        } catch (ValidationException) {
            $this->assertNull($sub->fresh()->funds_received_at);
        }
        $this->expectException(HttpException::class);
        app(BankPaymentService::class)->confirm($sub, $this->accountant, $this->bankData('2026-09-15'));
    }

    public function test_bank_coordinates_are_frozen_when_request_is_created(): void
    {
        $sub = $this->bank();
        BankDetail::first()->update(['iban' => 'NEW-ACCOUNT']);
        $this->assertSame('IBAN-TEST-001', $sub->fresh()->bank_snapshot['iban']);
    }

    public function test_proof_upload_does_not_confirm_and_is_private_until_scanned(): void
    {
        Storage::fake('payment_private');
        config(['payments.scanner_binary' => null]);
        $sub = $this->bank();
        $this->actingAs($this->client)->post('/api/subscriptions/'.$sub->id.'/proofs', [
            'file' => UploadedFile::fake()->image('justificatif.png'),
            'declared_date' => '2026-09-16', 'declared_amount' => 75750,
        ])->assertCreated()->assertJsonPath('proof.scan_status', 'quarantined')->assertJsonMissingPath('proof.path');
        $proof = PaymentProof::firstOrFail();
        $this->assertSame('En attente', $sub->fresh()->statut);
        $this->get('/api/payment-proofs/'.$proof->id.'/download')->assertStatus(423);
        Queue::assertNothingPushed();
        $this->actingAs(User::create(['first_name' => 'Test', 'last_name' => 'Payment', 'email' => bin2hex(random_bytes(8)).'@example.com', 'password' => 'TestingOnly-2026!']))->get('/api/payment-proofs/'.$proof->id.'/download')->assertNotFound();
        $this->get('/api/subscriptions/'.$sub->id.'/proofs')->assertNotFound();
        $proof->update(['scan_status' => 'clean']);
        $this->actingAs($this->client)->get('/api/payment-proofs/'.$proof->id.'/download')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_executable_disguised_as_pdf_is_rejected(): void
    {
        Storage::fake('payment_private');
        $sub = $this->bank();
        $this->actingAs($this->client)->postJson('/api/subscriptions/'.$sub->id.'/proofs', [
            'file' => UploadedFile::fake()->createWithContent('proof.pdf', '<?php system($_GET["cmd"]);'),
            'declared_date' => '2026-09-16', 'declared_amount' => 75750,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('payment_proofs', 0);
    }

    public function test_selected_bank_and_beneficiary_are_preserved_and_replay_cannot_change_account(): void
    {
        $bank = BankDetail::create(['bank_name' => 'Deuxième banque', 'beneficiary' => 'PEK bénéficiaire', 'iban' => 'ACCOUNT-2', 'is_active' => true]);
        $payload = ['product_id' => $this->product->id, 'investment_amount' => 75000, 'moyen_paiement' => 'bank_transfer', 'bank_detail_id' => $bank->id];
        $this->actingAs($this->client)->withHeader('Idempotency-Key', 'selected-bank')->postJson('/api/subscriptions', $payload)
            ->assertCreated()->assertJsonPath('subscription.bank_snapshot.id', $bank->id)
            ->assertJsonPath('subscription.bank_snapshot.beneficiary', 'PEK bénéficiaire');
        $this->postJson('/api/subscriptions', [...$payload, 'bank_detail_id' => BankDetail::first()->id])->assertConflict();
        $bank->update(['is_active' => false]);
        $this->withHeader('Idempotency-Key', 'inactive-bank')->postJson('/api/subscriptions', $payload)->assertUnprocessable();
    }

    public function test_array_phone_is_validation_error_not_server_error(): void
    {
        $this->actingAs($this->client)->withHeader('Idempotency-Key', 'invalid-phone')->postJson('/api/subscriptions', [
            'product_id' => $this->product->id, 'investment_amount' => 75000,
            'moyen_paiement' => 'orange_money', 'payment_phone' => ['699000001'],
        ])->assertUnprocessable()->assertJsonValidationErrors('payment_phone');
    }

    public function test_tampered_clean_proof_is_not_downloadable(): void
    {
        Storage::fake('payment_private');
        config(['payments.scanner_binary' => null]);
        $sub = $this->bank();
        $this->post('/api/subscriptions/'.$sub->id.'/proofs', [
            'file' => UploadedFile::fake()->image('proof.png'), 'declared_date' => '2026-09-16', 'declared_amount' => 75750,
        ])->assertCreated();
        $proof = PaymentProof::firstOrFail();
        $proof->update(['scan_status' => 'clean']);
        Storage::disk('payment_private')->put($proof->path, 'altered file');
        $this->get('/api/payment-proofs/'.$proof->id.'/download')->assertStatus(423);
    }

    public function test_simulation_is_blocked_in_production_even_when_flag_is_enabled(): void
    {
        config(['payments.s3p.simulation' => true]);
        $this->app->instance('env', 'production');
        $gateway = app(S3pGateway::class);
        $this->assertFalse($gateway->isSimulation());
        $this->assertFalse($gateway->available('orange_money'));
        $this->assertFalse($gateway->available('mtn_momo'));
    }

    public function test_simulation_is_blocked_with_persistent_database_configuration(): void
    {
        config(['payments.s3p.simulation' => true, 'database.connections.sqlite.database' => 'persistent.sqlite']);
        $this->assertFalse(app(S3pGateway::class)->isSimulation());
        config(['database.connections.sqlite.database' => ':memory:']);
    }

    public function test_reversed_mobile_payment_cannot_be_credited_again(): void
    {
        $this->s3pConfig();
        $this->fakeS3p();
        $sub = $this->mobile();
        $service = app(MobilePaymentService::class);
        $service->refresh($sub);
        $this->travel(11)->seconds();
        $this->fakeS3p(status: 'REVERSED');
        $this->assertSame('À vérifier', $service->refresh($sub)->statut);
        $this->travel(11)->seconds();
        $this->fakeS3p();
        $this->assertSame('À vérifier', $service->refresh($sub)->statut);
        Queue::assertPushed(ProcessSubscriptionReceipt::class, 1);
    }

    private function s3pConfig(): void
    {
        config(['payments.s3p.verified_timestamp_is_receipt' => true]);
        config(['payments.s3p.enabled' => true, 'payments.s3p.public_key' => 'test-public',
            'payments.s3p.secret_key' => 'test-secret', 'payments.s3p.services.orange_money' => '20001',
            'payments.s3p.merchants.orange_money' => 'TEST-ORANGE', 'payments.s3p.merchants.mtn_momo' => 'TEST-MTN',
            'payments.s3p.services.mtn_momo' => '20002', 'payments.s3p.webhook_secret' => 'callback-test']);
    }

    public function test_interactive_staging_success_cannot_credit_real_parts(): void
    {
        $this->s3pConfig();
        $this->fakeS3p();
        $sub = $this->mobile();
        $this->app->instance('env', 'local');
        $result = app(MobilePaymentService::class)->refresh($sub);
        $this->assertSame('success', $result->mobile_state);
        $this->assertSame('staging_only', $result->valuation_status);
        $this->assertSame('En attente', $result->statut);
        Queue::assertNothingPushed();
        $this->app->instance('env', 'testing');
    }

    public function test_production_never_offers_staging_payments(): void
    {
        $this->s3pConfig();
        $this->app->instance('env', 'production');
        $gateway = app(S3pGateway::class);
        $this->assertFalse($gateway->available('orange_money'));
        $this->assertFalse($gateway->available('mtn_momo'));
        $this->app->instance('env', 'testing');
    }

    private function mobile(): Subscription
    {
        ProductVl::firstOrCreate(['product_id' => $this->product->id, 'date_vl' => '2026-09-15'], ['vl' => 10000]);
        $this->actingAs($this->client)->withHeader('Idempotency-Key', 'mobile-test')->postJson('/api/subscriptions', [
            'product_id' => $this->product->id, 'investment_amount' => 75000,
            'moyen_paiement' => 'orange_money', 'payment_phone' => '237699000001',
        ])->assertCreated()->assertJsonPath('payment.provider', 's3p')->assertJsonMissingPath('subscription.payment_phone');

        return Subscription::where('idempotency_key', 'mobile-test')->firstOrFail();
    }

    public function test_webhook_schedules_post_response_finalization_without_client_check(): void
    {
        $this->s3pConfig();
        config(['payments.s3p.verified_timestamp_is_receipt' => false]);
        $this->fakeS3p();
        $sub = $this->mobile();
        $this->sendS3pCallback($sub)->assertOk();
        Bus::assertDispatchedAfterResponse(ProcessS3pCallbacks::class,
            fn ($job) => $job->subscriptionId === $sub->id);
        $this->app->call([new ProcessS3pCallbacks($sub->id), 'handle']);
        $this->assertSame('Succès', $sub->fresh()->statut);
        $this->assertSame('2026-09-16', $sub->fresh()->value_date->toDateString());
        $this->app->call([new ProcessS3pCallbacks($sub->id), 'handle']);
        Queue::assertPushed(ProcessSubscriptionReceipt::class, 1);
        $this->assertCount(1, Http::recorded(fn ($r) => str_contains($r->url(), '/collectstd')));
    }

    public function test_staging_opt_in_recovers_previously_processed_dated_payment(): void
    {
        $this->s3pConfig();
        config(['payments.s3p.verified_timestamp_is_receipt' => false]);
        $this->fakeS3p();
        $sub = $this->mobile();
        $this->app->instance('env', 'staging');
        $this->sendS3pCallback($sub)->assertOk();
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('staging_only', $sub->fresh()->valuation_status);
        config(['payments.s3p.credit_test_parts' => true]);
        $this->travel(61)->seconds();
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('Succès', $sub->fresh()->statut);
        Queue::assertPushed(ProcessSubscriptionReceipt::class, 1);
        $this->app->instance('env', 'testing');
    }

    public function test_test_credit_opt_in_never_allows_staging_funds_in_production(): void
    {
        $this->s3pConfig();
        $this->fakeS3p();
        $sub = $this->mobile();
        config(['payments.s3p.credit_test_parts' => true]);
        $this->app->instance('env', 'production');
        $this->assertTrue(S3pGateway::mustKeepTestFundsSeparate($sub));
        $this->app->instance('env', 'testing');
    }

    public function test_empty_provider_lookup_stays_pending_without_credit_or_second_debit(): void
    {
        $this->s3pConfig();
        $this->fakeS3p(timeout: true);
        $sub = $this->mobile();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 120]),
            '*/verifytx*' => Http::response([]),
        ]);
        $this->actingAs($this->client)->postJson('/api/subscriptions/'.$sub->id.'/check-status')
            ->assertStatus(202)->assertJsonPath('code', 'S3P_TRANSACTION_NOT_FOUND')
            ->assertJsonPath('status', 'pending');
        $this->assertSame('verification_required', $sub->fresh()->mobile_state);
        $this->assertSame('En attente', $sub->fresh()->statut);
        $this->assertNull($sub->fresh()->funds_received_at);
        app(MobilePaymentService::class)->start($sub->fresh());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/collectstd'));
        Queue::assertNothingPushed();
    }

    public function test_initial_failure_is_diagnosable_without_replaying_payment(): void
    {
        $this->s3pConfig();
        $this->fakeS3p(timeout: true);
        $sub = $this->mobile();
        $event = DB::table('payment_events')->where('subscription_id', $sub->id)
            ->where('type', 'mobile_check_required')->first();
        $details = json_decode($event->details, true);
        $this->assertSame('collect', $details['phase']);
        $this->assertSame('connection_error', $details['reason']);
        $before = count(Http::recorded());
        $state = $sub->fresh()->getAttributes();
        $this->artisan('payments:s3p-diagnose', ['subscription' => $sub->id])->assertSuccessful();
        $this->assertCount($before, Http::recorded());
        $this->assertSame($state, $sub->fresh()->getAttributes());
    }

    public function test_staging_timestamp_uses_utc_storage_and_business_day_nav(): void
    {
        $this->s3pConfig();
        config(['payments.s3p.verified_timestamp_is_receipt' => false,
            'payments.s3p.credit_test_parts' => true, 'payments.s3p.staging_use_provider_timestamp' => true]);
        $this->travelTo(now()->setDate(2026, 9, 15)->setTime(20, 0));
        $this->fakeS3p(timestamp: '2026-09-16T00:30:00+01:00');
        $sub = $this->mobile();
        $sub->forceFill(['mobile_state' => 'success', 'valuation_status' => 'awaiting_payment_date'])->save();
        $this->app->instance('env', 'staging');
        $this->travelTo(now()->setDate(2026, 9, 16)->setTime(12, 0));
        $this->artisan('payments:reconcile')->assertSuccessful();
        $sub->refresh();
        $this->assertSame('Succès', $sub->statut);
        $this->assertSame('2026-09-15 23:30:00', $sub->getRawOriginal('funds_received_at'));
        $this->assertSame('2026-09-16', $sub->value_date->toDateString());
        $this->assertSame('verifytx_staging', $sub->s3p_context['receipt_timestamp_source']);
        $this->assertSame('2026-09-16T00:30:00+01:00', $sub->s3p_context['receipt_timestamp_original']);
        $parts = $sub->nb_parts;
        $this->travel(11)->seconds();
        $callback = (object) ['ptn' => 'PTN-001', 'provider_status' => 'SUCCESS', 'provider_timestamp' => '2026-09-16T10:00:00+01:00'];
        app(MobilePaymentService::class)->refresh($sub, true, $callback);
        $this->assertSame($parts, $sub->fresh()->nb_parts);
        $this->assertDatabaseHas('payment_events', ['subscription_id' => $sub->id, 'type' => 'mobile_payment_date_conflict']);
        Queue::assertPushed(ProcessSubscriptionReceipt::class, 1);
        $this->app->instance('env', 'testing');
    }

    public function test_staging_timestamp_convention_is_blocked_in_production(): void
    {
        $this->s3pConfig();
        $this->fakeS3p();
        $sub = $this->mobile();
        config(['payments.s3p.credit_test_parts' => true, 'payments.s3p.staging_use_provider_timestamp' => true]);
        $this->app->instance('env', 'production');
        $this->assertFalse(S3pGateway::allowsStagingTimestamp($sub));
        $this->app->instance('env', 'testing');
    }

    public function test_staging_timestamp_still_waits_for_prior_nav(): void
    {
        $this->s3pConfig();
        config(['payments.s3p.verified_timestamp_is_receipt' => false,
            'payments.s3p.credit_test_parts' => true, 'payments.s3p.staging_use_provider_timestamp' => true]);
        $this->fakeS3p();
        $sub = $this->mobile();
        ProductVl::where('product_id', $this->product->id)->delete();
        $this->app->instance('env', 'staging');
        $result = app(MobilePaymentService::class)->refresh($sub);
        $this->assertSame('awaiting_nav', $result->valuation_status);
        $this->assertSame('En attente', $result->statut);
        $this->assertNotNull($result->funds_received_at);
        Queue::assertNothingPushed();
        ProductVl::create(['product_id' => $this->product->id, 'date_vl' => '2026-09-15', 'vl' => 12500]);
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('6.00000000', $sub->fresh()->nb_parts);
        Queue::assertPushed(ProcessSubscriptionReceipt::class, 1);
        $this->app->instance('env', 'testing');
    }

    public function test_staging_timestamp_never_replaces_invalid_date_with_now(): void
    {
        $this->s3pConfig();
        config(['payments.s3p.verified_timestamp_is_receipt' => false,
            'payments.s3p.credit_test_parts' => true, 'payments.s3p.staging_use_provider_timestamp' => true]);
        $this->fakeS3p(timestamp: 'invalid');
        $sub = $this->mobile();
        $this->app->instance('env', 'staging');
        try {
            app(MobilePaymentService::class)->refresh($sub);
            $this->fail('Invalid provider date must not credit parts.');
        } catch (\RuntimeException $e) {
            $this->assertNull($sub->fresh()->funds_received_at);
            $this->assertSame('En attente', $sub->fresh()->statut);
        } finally {
            $this->app->instance('env', 'testing');
        }
        Queue::assertNothingPushed();
    }

    private function fakeS3p(string $status = 'SUCCESS', int $amount = 75750, bool $timeout = false, ?string $timestamp = null): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($status, $amount, $timeout, $timestamp) {
            $isMtn = str_contains($request->url(), '20002') || Subscription::where('mobile_provider', 'mtn_momo')->exists();
            $merchant = $isMtn ? 'TEST-MTN' : 'TEST-ORANGE';
            $service = $isMtn ? '20002' : '20001';
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 120]);
            }
            if (str_contains($request->url(), '/cashout')) {
                return Http::response([['payItemId' => 'collection-item', 'merchant' => $merchant, 'serviceid' => $service, 'localCur' => 'XAF', 'amountType' => 'CUSTOM']]);
            }
            if (str_contains($request->url(), '/quotestd')) {
                return Http::response(['quoteId' => 'quote-1', 'priceLocalCur' => 75750, 'localCur' => 'XAF', 'payItemId' => 'collection-item', 'expiresIn' => 120]);
            }
            if (str_contains($request->url(), '/collectstd')) {
                if ($timeout) {
                    throw new ConnectionException('Simulated lost response');
                }

                return Http::response(['ptn' => 'PTN-001', 'status' => 'PENDING']);
            }
            if (str_contains($request->url(), '/verifytx')) {
                return Http::response([
                    'trid' => Subscription::whereNotNull('s3p_reference')->first()->s3p_reference,
                    'ptn' => 'PTN-001', 'payItemId' => 'collection-item', 'status' => $status,
                    'merchant' => $merchant, 'serviceid' => $service, 'errorCode' => $status === 'REVERSED' ? 3 : ($status === 'ERRORED' ? 703202 : 0),
                    'timestamp' => $timestamp ?? now()->toIso8601String(),
                    'priceLocalCur' => $amount, 'localCur' => 'XAF',
                ]);
            }
            throw new \RuntimeException('Unexpected HTTP request');
        });
    }

    public function test_mobile_confirmation_is_provider_verified_and_idempotent(): void
    {
        $this->s3pConfig();
        $this->fakeS3p();
        $sub = $this->mobile();
        $this->assertSame('En attente', $sub->statut);
        $this->assertStringNotContainsString('237699000001', $sub->getRawOriginal('payment_phone'));
        $payments = app(MobilePaymentService::class);
        $payments->start($sub);
        $result = $payments->refresh($sub);
        $this->assertSame('Succès', $result->statut);
        $this->travel(11)->seconds();
        $payments->refresh($sub);
        Queue::assertPushed(ProcessSubscriptionReceipt::class, 1);
        $collectCalls = Http::recorded(fn ($r) => str_contains($r->url(), '/collectstd'));
        $this->assertCount(1, $collectCalls);
    }

    public function test_timeout_cannot_trigger_second_debit_and_is_recovered_by_reference(): void
    {
        $this->s3pConfig();
        $this->fakeS3p(timeout: true);
        $sub = $this->mobile();
        $this->assertSame('verification_required', $sub->mobile_state);
        $reference = $sub->s3p_reference;
        $payments = app(MobilePaymentService::class);
        $payments->start($sub);
        $this->assertSame($reference, $sub->fresh()->s3p_reference);
        $result = $payments->refresh($sub);
        $this->assertSame('Succès', $result->statut);
    }

    public function test_signed_webhook_cannot_override_wrong_provider_amount(): void
    {
        $this->s3pConfig();
        $this->fakeS3p(amount: 1);
        $sub = $this->mobile();
        $raw = json_encode(['trid' => $sub->s3p_reference, 'status' => 'SUCCESS', 'timestamp' => '2026-09-16 12:00:00', 'errorCode' => '0']);
        $this->call('POST', '/api/s3p/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PTN' => 'PTN-001', 'HTTP_X_DELIVERY' => 'd811b35f-ddfe-4e1d-b4e7-f60d1d8e9b53',
            'HTTP_X_SIGNATURE' => hash_hmac('sha1', $raw, 'callback-test')], $raw)->assertOk();
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertDatabaseHas('s3p_callback_inbox', ['subscription_id' => $sub->id, 'processed_at' => null, 'attempts' => 1]);
        $this->assertSame('En attente', $sub->fresh()->statut);
        Queue::assertNothingPushed();
    }

    public function test_unsigned_webhook_is_rejected_without_provider_call(): void
    {
        $this->s3pConfig();
        Http::fake();
        $this->postJson('/api/s3p/webhook', ['trid' => 'invented', 'status' => 'SUCCESS'])->assertForbidden();
        Http::assertNothingSent();
    }

    private function sendS3pCallback(Subscription $sub, string $status = 'SUCCESS', string $ptn = 'PTN-001')
    {
        $raw = json_encode(['timestamp' => '2026-09-16T12:00:00+00:00', 'trid' => $sub->s3p_reference,
            'errorCode' => $status === 'REVERSED' ? '3' : '0', 'status' => $status]);

        return $this->call('POST', '/api/s3p/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PTN' => $ptn, 'HTTP_X_DELIVERY' => 'd811b35f-ddfe-4e1d-b4e7-f60d1d8e9b53',
            'HTTP_X_SIGNATURE' => hash_hmac('sha1', $raw, 'callback-test')], $raw);
    }

    public function test_callback_is_durable_deduplicated_and_never_calls_provider_in_http_request(): void
    {
        $this->s3pConfig();
        $this->fakeS3p();
        $sub = $this->mobile();
        $before = count(Http::recorded());
        $this->sendS3pCallback($sub)->assertOk();
        $this->sendS3pCallback($sub)->assertOk();
        $this->assertCount($before, Http::recorded());
        $this->assertDatabaseCount('s3p_callback_inbox', 1);
        $this->assertSame('En attente', $sub->fresh()->statut);
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('Succès', $sub->fresh()->statut);
        $this->assertNotNull(DB::table('s3p_callback_inbox')->value('processed_at'));
        $this->artisan('payments:reconcile')->assertSuccessful();
        Queue::assertPushed(ProcessSubscriptionReceipt::class, 1);
    }

    public function test_callback_with_wrong_ptn_is_rejected(): void
    {
        $this->s3pConfig();
        $this->fakeS3p();
        $sub = $this->mobile();
        $this->sendS3pCallback($sub, ptn: 'WRONG-PTN')->assertConflict();
        $this->assertDatabaseCount('s3p_callback_inbox', 0);
    }

    public function test_signed_callback_with_conflicting_error_code_is_rejected(): void
    {
        $this->s3pConfig();
        $this->fakeS3p();
        $sub = $this->mobile();
        $raw = json_encode(['timestamp' => now()->toIso8601String(), 'trid' => $sub->s3p_reference,
            'status' => 'SUCCESS', 'errorCode' => '703202']);
        $this->call('POST', '/api/s3p/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json', 'HTTP_X_PTN' => 'PTN-001',
            'HTTP_X_DELIVERY' => 'd811b35f-ddfe-4e1d-b4e7-f60d1d8e9b53',
            'HTTP_X_SIGNATURE' => hash_hmac('sha1', $raw, 'callback-test')], $raw)->assertUnprocessable();
        $this->assertDatabaseCount('s3p_callback_inbox', 0);
        $this->assertNull($sub->fresh()->funds_received_at);
    }

    public function test_conflicting_terminal_callback_cannot_credit_parts(): void
    {
        $this->s3pConfig();
        $this->fakeS3p();
        $sub = $this->mobile();
        $callback = (object) ['ptn' => 'PTN-001', 'provider_status' => 'ERRORED', 'provider_timestamp' => now()->toIso8601String()];
        try {
            app(MobilePaymentService::class)->refresh($sub, true, $callback);
            $this->fail('A conflicting callback must remain unresolved.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('rapprocher', $e->getMessage());
        }
        $this->assertNull($sub->fresh()->funds_received_at);
        Queue::assertNothingPushed();
    }

    public function test_legacy_callback_is_supported_but_tampered_signed_body_is_rejected(): void
    {
        $this->s3pConfig();
        $this->fakeS3p();
        $sub = $this->mobile();
        $raw = json_encode(['timestamp' => '2026-09-16 12:00:00', 'trid' => $sub->s3p_reference, 'status' => 'ERROR', 'errorCode' => null]);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_PTN' => 'PTN-001',
            'HTTP_X_DELIVERY' => 'd811b35f-ddfe-4e1d-b4e7-f60d1d8e9b53', 'HTTP_X_SIGNATURE' => hash_hmac('sha1', $raw, 'callback-test')];
        $this->call('POST', '/api/s3p/webhook', [], [], [], $headers, str_replace('ERROR', 'SUCCESS', $raw))->assertForbidden();
        $this->assertDatabaseCount('s3p_callback_inbox', 0);
        $this->call('POST', '/api/s3p/webhook', [], [], [], $headers, $raw)->assertOk();
        $this->assertDatabaseHas('s3p_callback_inbox', ['provider_status' => 'ERRORED', 'processed_at' => null]);
        $this->assertSame('En attente', $sub->fresh()->statut);
    }

    public function test_callback_throttled_by_recent_poll_is_retried_and_not_lost(): void
    {
        $this->s3pConfig();
        $this->fakeS3p(status: 'PENDING');
        $sub = $this->mobile();
        app(MobilePaymentService::class)->refresh($sub);
        $this->fakeS3p();
        $this->sendS3pCallback($sub)->assertOk();
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertNull(DB::table('s3p_callback_inbox')->value('processed_at'));
        $this->assertSame('En attente', $sub->fresh()->statut);
        $this->travel(61)->seconds();
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('Succès', $sub->fresh()->statut);
    }

    public function test_provider_processing_time_is_not_assumed_to_be_receipt_time(): void
    {
        $this->s3pConfig();
        $this->fakeS3p();
        config(['payments.s3p.verified_timestamp_is_receipt' => false]);
        $sub = $this->mobile();
        $result = app(MobilePaymentService::class)->refresh($sub);
        $this->assertSame('success', $result->mobile_state);
        $this->assertSame('awaiting_payment_date', $result->valuation_status);
        $this->assertNull($result->value_date);
        $this->assertSame('En attente', $result->statut);
        Queue::assertNothingPushed();
        $this->sendS3pCallback($sub)->assertOk();
        $this->travel(11)->seconds();
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('Succès', $sub->fresh()->statut);
        $this->assertSame('2026-09-16', $sub->fresh()->value_date->toDateString());
    }

    public function test_mtn_uses_its_own_service_and_merchant(): void
    {
        $this->s3pConfig();
        $this->fakeS3p();
        ProductVl::create(['product_id' => $this->product->id, 'date_vl' => '2026-09-15', 'vl' => 10000]);
        $this->actingAs($this->client)->withHeader('Idempotency-Key', 'mtn-test')->postJson('/api/subscriptions', [
            'product_id' => $this->product->id, 'investment_amount' => 75000, 'moyen_paiement' => 'mtn_momo', 'payment_phone' => '+237 677 000 001',
        ])->assertCreated();
        $sub = Subscription::where('idempotency_key', 'mtn-test')->firstOrFail();
        $this->assertSame('TEST-MTN', $sub->s3p_context['merchant']);
        $this->assertSame('20002', $sub->s3p_context['serviceid']);
        $this->assertSame('Succès', app(MobilePaymentService::class)->refresh($sub)->statut);
    }

    public function test_delayed_mobile_confirmation_uses_provider_date_and_waits_for_prior_nav(): void
    {
        $this->s3pConfig();
        $this->fakeS3p(timestamp: '2026-09-12T10:00:00+00:00');
        $this->travelTo(now()->setDate(2026, 9, 10));
        $sub = $this->mobile();
        $this->travelTo(now()->setDate(2026, 9, 16));
        $result = app(MobilePaymentService::class)->refresh($sub);
        $this->assertSame('2026-09-12', $result->value_date->toDateString());
        $this->assertSame('En attente', $result->statut);
        $this->assertSame('awaiting_nav', $result->valuation_status);
        Queue::assertNothingPushed();
        ProductVl::create(['product_id' => $this->product->id, 'date_vl' => '2026-09-11', 'vl' => 12500]);
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('6.00000000', $sub->fresh()->nb_parts);
        $this->assertSame('Succès', $sub->fresh()->statut);
    }

    public function test_mobile_confirmation_without_unambiguous_date_cannot_credit_parts(): void
    {
        $this->s3pConfig();
        $this->fakeS3p(timestamp: '');
        $sub = $this->mobile();
        try {
            app(MobilePaymentService::class)->refresh($sub);
            $this->fail('Missing date was accepted');
        } catch (\RuntimeException) {
            $this->assertSame('En attente', $sub->fresh()->statut);
            $this->assertNull($sub->fresh()->funds_received_at);
            Queue::assertNothingPushed();
        }
    }

    public function test_mobile_reversal_while_waiting_for_nav_is_not_credited_by_reconciliation(): void
    {
        $this->s3pConfig();
        $this->fakeS3p();
        $sub = $this->mobile();
        ProductVl::where('product_id', $this->product->id)->delete();
        $service = app(MobilePaymentService::class);
        $this->assertSame('awaiting_nav', $service->refresh($sub)->valuation_status);
        $this->travel(11)->seconds();
        $this->fakeS3p(status: 'REVERSED');
        $service->refresh($sub);
        ProductVl::create(['product_id' => $this->product->id, 'date_vl' => '2026-09-15', 'vl' => 12500]);
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('À vérifier', $sub->fresh()->statut);
        Queue::assertNothingPushed();
    }

    public function test_unconfigured_mobile_payment_does_not_create_subscription(): void
    {
        config(['payments.s3p.enabled' => false, 'payments.s3p.simulation' => false]);
        $this->actingAs($this->client)->withHeader('Idempotency-Key', 'disabled-mobile')->postJson('/api/subscriptions', [
            'product_id' => $this->product->id, 'investment_amount' => 75000,
            'moyen_paiement' => 'mtn_momo', 'payment_phone' => '237677000001',
        ])->assertStatus(503);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_mobile_money_simulation_mode_enables_and_completes_payment_flow(): void
    {
        config(['payments.s3p.simulation' => true, 'payments.s3p.enabled' => false]);
        ProductVl::create(['product_id' => $this->product->id, 'date_vl' => '2026-09-15', 'vl' => 10000]);

        $this->getJson('/api/payment-options')->assertOk()
            ->assertJsonPath('orange_money', true)
            ->assertJsonPath('mtn_momo', true);

        // Test subscribing with 9-digit phone format (which automatically normalizes to 237699000001)
        $response = $this->actingAs($this->client)->withHeader('Idempotency-Key', 'sim-mobile-test')->postJson('/api/subscriptions', [
            'product_id' => $this->product->id,
            'investment_amount' => 75000,
            'moyen_paiement' => 'orange_money',
            'payment_phone' => '699000001',
        ])->assertCreated();

        $response->assertJsonPath('payment.provider', 's3p');

        $sub = Subscription::where('idempotency_key', 'sim-mobile-test')->firstOrFail();
        $this->assertSame('Succès', $sub->statut);
        $this->assertSame('success', $sub->mobile_state);
        $this->assertNotNull($sub->s3p_reference);
        $this->assertNotNull($sub->s3p_ptn);
        Queue::assertPushed(ProcessSubscriptionReceipt::class, 1);
    }
}
