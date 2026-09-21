<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\EnkapPaymentGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnkapPaymentGatewayTest extends TestCase
{
    public function test_it_authenticates_places_an_exact_xaf_order_and_normalizes_the_response(): void
    {
        config()->set([
            'services.maviance.enabled' => true,
            'services.maviance.client_id' => 'client-id',
            'services.maviance.client_secret' => 'client-secret',
            'services.maviance.base_url' => 'https://api.enkap.test',
            'services.maviance.checkout_ttl_minutes' => 30,
        ]);
        Cache::flush();

        Http::fake([
            'https://api.enkap.test/token' => Http::response([
                'access_token' => 'access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'https://api.enkap.test/api/order' => Http::response([
                'orderTransactionId' => 'enkap-tx-42',
                'merchantReferenceId' => 'FCP-ABC-A1',
                'redirectUrl' => 'https://payment.enkap.cm/payment/ui/auth?stxid=enkap-tx-42',
            ], 201),
        ]);

        $subscription = new Subscription([
            'montant_total' => 50500,
            'reference_transaction' => 'FCP-ABC',
        ]);
        $subscription->id = 42;
        $subscription->setRelation('user', new User([
            'first_name' => 'Jean',
            'last_name' => 'Mbarga',
            'email' => 'jean@example.com',
            'phone' => '+237600000000',
        ]));
        $subscription->setRelation('product', new Product(['libelle' => 'FCP Test']));

        $state = (new EnkapPaymentGateway)->createOrder($subscription, 1);

        $this->assertSame('enkap-tx-42', $state['id']);
        $this->assertSame('FCP-ABC-A1', $state['reference']);
        $this->assertSame(50500, $state['amount_total']);
        $this->assertSame('XAF', $state['currency']);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api.enkap.test/api/order') {
                return false;
            }

            return $request->hasHeader('Authorization', 'Bearer access-token')
                && $request['merchantReference'] === 'FCP-ABC-A1'
                && $request['totalAmount'] === 50500
                && $request['currency'] === 'XAF'
                && ! array_key_exists('items', $request->data());
        });
    }

    public function test_it_reads_back_the_provider_amount_currency_reference_and_status(): void
    {
        config()->set([
            'services.maviance.enabled' => true,
            'services.maviance.client_id' => 'client-id',
            'services.maviance.client_secret' => 'client-secret',
            'services.maviance.base_url' => 'https://api.enkap.test',
        ]);
        Cache::flush();

        Http::fake([
            'https://api.enkap.test/token' => Http::response(['access_token' => 'access-token']),
            'https://api.enkap.test/api/order*' => Http::response([
                'id' => ['uuid' => 'enkap-tx-42'],
                'paymentStatus' => 'CONFIRMED',
                'paymentProviderName' => 'mtn',
                'order' => [
                    'merchantReference' => 'FCP-ABC-A1',
                    'totalAmount' => 50500,
                    'currency' => 'XAF',
                ],
            ]),
        ]);

        $state = (new EnkapPaymentGateway)->retrieveOrder('enkap-tx-42');

        $this->assertSame('enkap-tx-42', $state['id']);
        $this->assertSame('CONFIRMED', $state['status']);
        $this->assertSame('FCP-ABC-A1', $state['reference']);
        $this->assertSame(50500, $state['amount_total']);
        $this->assertSame('XAF', $state['currency']);
        $this->assertSame('mtn', $state['provider_name']);
    }
}
