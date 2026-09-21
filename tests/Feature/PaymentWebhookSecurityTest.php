<?php

namespace Tests\Feature;

use App\Jobs\ProcessSubscriptionReceipt;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_stripe_checkout_webhook_confirms_exact_payment_once(): void
    {
        Queue::fake();
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
        $subscription = $this->stripeSubscription();
        $payload = $this->stripeCheckoutPayload($subscription, 50500);

        $this->postSignedStripeWebhook($payload)->assertOk()->assertJsonPath('status', 'received');
        $this->postSignedStripeWebhook($payload)->assertOk()->assertJsonPath('status', 'received');

        $subscription->refresh();
        $this->assertSame('Succès', $subscription->statut);
        $this->assertSame('pi_test_checkout_paid', $subscription->stripe_payment_intent_id);
        $this->assertNotNull($subscription->payment_confirmed_at);
        Queue::assertPushed(ProcessSubscriptionReceipt::class, 1);
    }

    public function test_signed_stripe_checkout_webhook_rejects_mismatched_amount(): void
    {
        Queue::fake();
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
        $subscription = $this->stripeSubscription();

        $this->postSignedStripeWebhook($this->stripeCheckoutPayload($subscription, 1))
            ->assertStatus(409)
            ->assertJsonPath('status', 'rejected');

        $this->assertSame('En attente', $subscription->fresh()->statut);
        Queue::assertNothingPushed();
    }

    public function test_maviance_requires_vendor_signature_and_exact_amount(): void
    {
        Queue::fake();
        config([
            'services.maviance.enabled' => true,
            'services.maviance.public_key' => 'public-app-key',
            'services.maviance.private_key' => 'private-server-key',
        ]);
        $subscription = $this->subscription();

        $payload = $this->maviancePayload($subscription, '50500');
        $this->postJson('/api/maviance/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'received');

        $subscription->refresh();
        $this->assertSame('Succès', $subscription->statut);
        $this->assertSame('MCP-REFERENCE-001', $subscription->maviance_transaction_ref);
        $this->assertNotNull($subscription->payment_confirmed_at);
        $this->assertNotNull(DB::table('subscriptions')->where('id', $subscription->id)->value('provider_payload_hash'));
    }

    public function test_validly_signed_but_mismatched_maviance_amount_is_rejected(): void
    {
        Queue::fake();
        config([
            'services.maviance.enabled' => true,
            'services.maviance.public_key' => 'public-app-key',
            'services.maviance.private_key' => 'private-server-key',
        ]);
        $subscription = $this->subscription();

        $this->postJson('/api/maviance/webhook', $this->maviancePayload($subscription, '1'))
            ->assertStatus(409)
            ->assertJsonPath('status', 'rejected');

        $this->assertSame('En attente', $subscription->fresh()->statut);
    }

    public function test_maviance_query_secret_cannot_replace_callback_signature(): void
    {
        config([
            'services.maviance.enabled' => true,
            'services.maviance.public_key' => 'public-app-key',
            'services.maviance.private_key' => 'private-server-key',
        ]);
        $subscription = $this->subscription();
        $payload = $this->maviancePayload($subscription, '50500');
        unset($payload['signature']);

        $this->postJson('/api/maviance/webhook?key=private-server-key', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('signature');
    }

    private function subscription(): Subscription
    {
        $user = User::create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'password' => bcrypt('a-secure-password'),
        ]);
        $product = Product::create([
            'libelle' => 'FCP Test',
            'description' => 'Fonds de test',
            'vl' => 10000,
            'seuil_minimum' => 50000,
            'is_active' => true,
        ]);

        return Subscription::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'nb_parts' => 5,
            'prix_unitaire' => 10000,
            'montant_total' => 50500,
            'moyen_paiement' => 'orange_money',
            'statut' => 'En attente',
            'reference_transaction' => 'FCP-REFERENCE-001',
        ]);
    }

    private function stripeSubscription(): Subscription
    {
        $user = User::create([
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'grace@example.test',
            'password' => bcrypt('a-secure-password'),
        ]);
        $product = Product::create([
            'libelle' => 'FCP Stripe Test',
            'description' => 'Fonds de test',
            'vl' => 10000,
            'seuil_minimum' => 50000,
            'is_active' => true,
        ]);

        return Subscription::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'nb_parts' => 5,
            'prix_unitaire' => 10000,
            'montant_total' => 50500,
            'moyen_paiement' => 'card',
            'statut' => 'En attente',
            'reference_transaction' => 'FCP-STRIPE-001',
            'stripe_checkout_session_id' => 'cs_test_checkout_paid',
            'payment_currency' => 'XAF',
        ]);
    }

    private function stripeCheckoutPayload(Subscription $subscription, int $amount): array
    {
        return [
            'id' => 'evt_test_checkout_paid',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'created' => now()->timestamp,
            'data' => [
                'object' => [
                    'id' => 'cs_test_checkout_paid',
                    'object' => 'checkout.session',
                    'client_reference_id' => (string) $subscription->id,
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_test_checkout_paid',
                    'currency' => 'xaf',
                    'amount_total' => $amount,
                    'metadata' => [
                        'subscription_id' => (string) $subscription->id,
                        'reference_transaction' => $subscription->reference_transaction,
                    ],
                ],
            ],
        ];
    }

    private function postSignedStripeWebhook(array $event): TestResponse
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_secret');

        return $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $payload,
        );
    }

    private function maviancePayload(Subscription $subscription, string $amount): array
    {
        $payload = [
            'application' => 'public-app-key',
            'app_transaction_ref' => $subscription->reference_transaction,
            'transaction_ref' => 'MCP-REFERENCE-001',
            'transaction_type' => 'PAYIN',
            'transaction_amount' => $amount,
            'transaction_currency' => 'XAF',
            'transaction_operator' => 'CM_OM',
            'transaction_status' => 'SUCCESS',
        ];
        $payload['signature'] = md5(
            $payload['transaction_ref']
            .$payload['transaction_type']
            .$payload['transaction_amount']
            .$payload['transaction_currency']
            .$payload['transaction_operator']
            .'private-server-key'
        );

        return $payload;
    }
}
