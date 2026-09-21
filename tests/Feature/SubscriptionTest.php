<?php

namespace Tests\Feature;

use App\Jobs\ProcessSubscriptionReceipt;
use App\Models\BankDetail;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\LocalPaymentGateway;
use App\Services\Payments\PaymentCheckoutGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeLocalPaymentGateway;
use Tests\Fakes\FakePaymentCheckoutGateway;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Product $product;

    protected BankDetail $bankDetail;

    protected FakePaymentCheckoutGateway $paymentGateway;

    protected FakeLocalPaymentGateway $localPaymentGateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentGateway = new FakePaymentCheckoutGateway;
        $this->app->instance(PaymentCheckoutGateway::class, $this->paymentGateway);
        $this->localPaymentGateway = new FakeLocalPaymentGateway;
        $this->app->instance(LocalPaymentGateway::class, $this->localPaymentGateway);

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
            'om_instructions' => 'OM step 1, step 2',
            'momo_instructions' => 'MoMo step 1, step 2',
            'bank_instructions' => 'Virement step 1, step 2',
        ]);
    }

    public function test_server_calculates_subscription_amount_and_ignores_client_amount(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'subscription-001')
            ->postJson('/api/subscriptions', [
                'product_id' => $this->product->id,
                'nb_parts' => 5,
                'moyen_paiement' => 'bank_transfer',
                'montant_total' => 1,
            ]);

        $response->assertCreated()
            ->assertJsonPath('subscription.prix_unitaire', '10000.0000')
            ->assertJsonPath('subscription.montant_total', '50500.00')
            ->assertJsonPath('subscription.moyen_paiement', 'bank_transfer');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'nb_parts' => 5,
            'montant_total' => 50500,
            'idempotency_key' => 'subscription-001',
            'statut' => 'En attente',
        ]);
    }

    public function test_xaf_payable_amount_is_rounded_to_whole_francs(): void
    {
        $this->product->update(['vl' => 10000.25]);

        $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'xaf-zero-decimal')
            ->postJson('/api/subscriptions', [
                'product_id' => $this->product->id,
                'nb_parts' => 5,
                'moyen_paiement' => 'bank_transfer',
            ])
            ->assertCreated()
            ->assertJsonPath('subscription.montant_total', '50501.00');

        $this->assertDatabaseHas('subscriptions', [
            'idempotency_key' => 'xaf-zero-decimal',
            'montant_total' => 50501,
        ]);
    }

    public function test_idempotency_key_prevents_duplicate_subscription(): void
    {
        $payload = [
            'product_id' => $this->product->id,
            'nb_parts' => 5,
            'moyen_paiement' => 'bank_transfer',
        ];

        $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'same-request')
            ->postJson('/api/subscriptions', $payload)
            ->assertCreated();

        $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'same-request')
            ->postJson('/api/subscriptions', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Cette demande avait déjà été enregistrée.');

        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_idempotency_key_cannot_be_reused_for_different_request(): void
    {
        $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'same-key-different-payload')
            ->postJson('/api/subscriptions', [
                'product_id' => $this->product->id,
                'nb_parts' => 5,
                'moyen_paiement' => 'bank_transfer',
            ])
            ->assertCreated();

        $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'same-key-different-payload')
            ->postJson('/api/subscriptions', [
                'product_id' => $this->product->id,
                'nb_parts' => 6,
                'moyen_paiement' => 'bank_transfer',
            ])
            ->assertConflict();

        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_card_subscription_returns_a_hosted_checkout_url(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'stripe-checkout-001')
            ->postJson('/api/subscriptions', [
                'product_id' => $this->product->id,
                'nb_parts' => 5,
                'moyen_paiement' => 'card',
            ]);

        $response->assertCreated()
            ->assertJsonPath('payment.provider', 'stripe')
            ->assertJsonPath('payment.status', 'pending')
            ->assertJsonPath('payment.redirect_required', true)
            ->assertJsonPath('payment.checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_subscription_1_1');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->user->id,
            'moyen_paiement' => 'card',
            'stripe_checkout_session_id' => 'cs_test_subscription_1_1',
            'payment_attempt' => 1,
            'statut' => 'En attente',
        ]);
    }

    public function test_mobile_money_subscription_returns_the_enkap_hosted_checkout(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'enkap-checkout-001')
            ->postJson('/api/subscriptions', [
                'product_id' => $this->product->id,
                'nb_parts' => 5,
                'moyen_paiement' => 'mobile_money',
            ]);

        $response->assertCreated()
            ->assertJsonPath('payment.provider', 'enkap')
            ->assertJsonPath('payment.status', 'pending')
            ->assertJsonPath('payment.redirect_required', true)
            ->assertJsonPath('payment.checkout_url', 'https://payment.enkap.cm/payment/ui/auth?stxid=enkap_subscription_1_1');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->user->id,
            'moyen_paiement' => 'mobile_money',
            'maviance_transaction_ref' => 'enkap_subscription_1_1',
            'payment_attempt' => 1,
            'statut' => 'En attente',
        ]);
    }

    public function test_enkap_notification_never_trusts_the_unsigned_callback_status(): void
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'enkap-callback-001')
            ->postJson('/api/subscriptions', [
                'product_id' => $this->product->id,
                'nb_parts' => 5,
                'moyen_paiement' => 'mobile_money',
            ])
            ->assertCreated();

        $subscription = Subscription::firstOrFail();
        $reference = $subscription->enkap_merchant_reference;

        $this->putJson("/api/enkap/webhook/{$reference}", ['status' => 'CONFIRMED'])
            ->assertOk()
            ->assertJsonPath('status', 'received');
        $this->assertSame('En attente', $subscription->fresh()->statut);

        $this->localPaymentGateway->markPaid($subscription->maviance_transaction_ref);

        $this->putJson("/api/enkap/webhook/{$reference}", ['status' => 'CREATED'])
            ->assertOk();
        $this->assertSame('Succès', $subscription->fresh()->statut);
        Queue::assertPushed(ProcessSubscriptionReceipt::class, 1);
    }

    public function test_payment_status_only_confirms_provider_verified_payment(): void
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'stripe-status-001')
            ->postJson('/api/subscriptions', [
                'product_id' => $this->product->id,
                'nb_parts' => 5,
                'moyen_paiement' => 'card',
            ])
            ->assertCreated();

        $subscription = Subscription::firstOrFail();

        $this->actingAs($this->user)
            ->getJson("/api/subscriptions/{$subscription->id}/payment-status")
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->paymentGateway->markPaid($subscription->stripe_checkout_session_id);

        $this->actingAs($this->user)
            ->getJson("/api/subscriptions/{$subscription->id}/payment-status")
            ->assertOk()
            ->assertJsonPath('status', 'paid');

        $this->assertSame('Succès', $subscription->fresh()->statut);
        $this->assertNotNull($subscription->fresh()->payment_confirmed_at);
        Queue::assertPushed(ProcessSubscriptionReceipt::class, 1);
    }

    public function test_public_stripe_return_confirms_only_a_provider_verified_payment(): void
    {
        Queue::fake();
        $subscription = $this->createCardSubscriptionWithSession();
        $this->paymentGateway->markPaid($subscription->stripe_checkout_session_id);

        $this->postJson('/api/stripe/checkout-return', ['session_id' => $subscription->stripe_checkout_session_id])
            ->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonMissingPath('subscription');

        $confirmed = $subscription->fresh();
        $this->assertSame('Succès', $confirmed->statut);
        $this->assertSame('pi_test_paid', $confirmed->stripe_payment_intent_id);
        $this->assertNotNull($confirmed->payment_confirmed_at);
        Queue::assertPushed(ProcessSubscriptionReceipt::class, 1);
    }

    public function test_public_stripe_return_does_not_confirm_an_unpaid_session(): void
    {
        Queue::fake();
        $subscription = $this->createCardSubscriptionWithSession();

        $this->postJson('/api/stripe/checkout-return', ['session_id' => $subscription->stripe_checkout_session_id])
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->assertSame('En attente', $subscription->fresh()->statut);
        Queue::assertNothingPushed();
    }

    public function test_public_stripe_return_rejects_invalid_or_mismatched_sessions(): void
    {
        $this->postJson('/api/stripe/checkout-return', ['session_id' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session_id');

        $this->postJson('/api/stripe/checkout-return', ['session_id' => 'cs_test_unknown'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Session de paiement introuvable.');

        $subscription = $this->createCardSubscriptionWithSession();
        $this->paymentGateway->sessions[$subscription->stripe_checkout_session_id]['amount_total']++;

        $this->postJson('/api/stripe/checkout-return', ['session_id' => $subscription->stripe_checkout_session_id])
            ->assertConflict()
            ->assertJsonPath('message', 'La session de paiement ne correspond pas à cette souscription.');

        $this->assertSame('En attente', $subscription->fresh()->statut);
    }

    public function test_subscription_requires_validated_kyc(): void
    {
        $uncompletedUser = User::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($uncompletedUser)
            ->withHeader('Idempotency-Key', 'unvalidated-001')
            ->postJson('/api/subscriptions', [
                'product_id' => $this->product->id,
                'nb_parts' => 5,
                'moyen_paiement' => 'bank_transfer',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Votre dossier KYC doit être validé avant toute souscription.');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_minimum_subscription_threshold_is_enforced(): void
    {
        $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'below-minimum')
            ->postJson('/api/subscriptions', [
                'product_id' => $this->product->id,
                'nb_parts' => 4,
                'moyen_paiement' => 'bank_transfer',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Le seuil minimum de souscription de 50 000 FCFA n’est pas atteint.');
    }

    public function test_operator_specific_mobile_payment_requires_wallet_number(): void
    {
        $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'fake-mobile-money')
            ->postJson('/api/subscriptions', [
                'product_id' => $this->product->id,
                'nb_parts' => 5,
                'moyen_paiement' => 'orange_money',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_phone');
    }

    public function test_transition_to_success_is_idempotent(): void
    {
        Queue::fake();

        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'nb_parts' => 5,
            'prix_unitaire' => $this->product->vl,
            'montant_total' => 50500,
            'moyen_paiement' => 'bank_transfer',
            'statut' => 'En attente',
            'reference_transaction' => 'FCP-TEST123',
        ]);

        $subscription->update(['statut' => 'Succès']);
        $subscription->update(['statut' => 'Succès']);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'Souscription Validée ✅',
            'body' => 'Votre souscription FCP-TEST123 pour FCP Kori Sérénité a été validée. Vos parts sont créditées.',
        ]);
        $this->assertDatabaseCount('notifications', 1);
        Queue::assertPushed(ProcessSubscriptionReceipt::class, 1);
    }

    private function createCardSubscriptionWithSession(): Subscription
    {
        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'nb_parts' => 5,
            'prix_unitaire' => $this->product->vl,
            'montant_total' => 50500,
            'moyen_paiement' => 'card',
            'statut' => 'En attente',
            'reference_transaction' => 'FCP-RETURN-'.strtoupper(bin2hex(random_bytes(4))),
            'payment_attempt' => 1,
            'payment_currency' => 'XAF',
        ]);
        $state = $this->paymentGateway->createSession($subscription, 1);
        $subscription->update([
            'stripe_checkout_session_id' => $state['id'],
            'payment_initiated_at' => now(),
        ]);

        return $subscription->fresh();
    }
}
