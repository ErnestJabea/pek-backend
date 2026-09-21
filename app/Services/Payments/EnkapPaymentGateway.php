<?php

namespace App\Services\Payments;

use App\Models\Subscription;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EnkapPaymentGateway implements LocalPaymentGateway
{
    public function createOrder(Subscription $subscription, int $attempt): array
    {
        $this->assertConfigured();
        $subscription->loadMissing(['user', 'product']);

        $expiresAt = now()->addMinutes((int) config('services.maviance.checkout_ttl_minutes', 30));
        $reference = substr($subscription->reference_transaction.'-A'.$attempt, 0, 36);
        $user = $subscription->user;

        $payload = array_filter([
            'currency' => 'XAF',
            'customerName' => trim((string) $user->first_name.' '.(string) $user->last_name),
            'description' => 'Souscription PEK '.$subscription->product->libelle,
            'email' => (string) $user->email,
            'expiryDate' => $expiresAt->copy()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'langKey' => 'fr',
            'merchantReference' => $reference,
            'phoneNumber' => $user->phone ? (string) $user->phone : null,
            'totalAmount' => (int) round((float) $subscription->montant_total),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $data = $this->request()
            ->post($this->baseUrl().'/api/order', $payload)
            ->throw()
            ->json();

        $state = [
            'id' => (string) ($data['orderTransactionId'] ?? ''),
            'url' => (string) ($data['redirectUrl'] ?? ''),
            'status' => 'CREATED',
            'amount_total' => (int) $payload['totalAmount'],
            'currency' => 'XAF',
            'expires_at' => $expiresAt->timestamp,
            'reference' => (string) ($data['merchantReferenceId'] ?? ''),
            'provider_name' => null,
        ];

        if ($state['id'] === '' || $state['url'] === '' || ! hash_equals($reference, $state['reference'])) {
            throw new RuntimeException('e-nkap a retourné une commande de paiement incomplète ou incohérente.');
        }

        return $state;
    }

    public function retrieveOrder(string $transactionId): array
    {
        $this->assertConfigured();

        $data = $this->request(retryable: true)
            ->get($this->baseUrl().'/api/order', ['txid' => $transactionId])
            ->throw()
            ->json();
        $order = is_array($data['order'] ?? null) ? $data['order'] : [];

        return [
            'id' => (string) (data_get($data, 'id.uuid') ?: data_get($order, 'id.uuid') ?: $transactionId),
            'url' => '',
            'status' => strtoupper((string) ($data['paymentStatus'] ?? '')),
            'amount_total' => (int) round((float) ($order['totalAmount'] ?? 0)),
            'currency' => strtoupper((string) ($order['currency'] ?? '')),
            'expires_at' => null,
            'reference' => (string) ($order['merchantReference'] ?? ''),
            'provider_name' => isset($data['paymentProviderName']) ? (string) $data['paymentProviderName'] : null,
        ];
    }

    private function request(bool $retryable = false): PendingRequest
    {
        $request = Http::acceptJson()
            ->withToken($this->accessToken())
            ->timeout(15)
            ->connectTimeout(5);

        return $retryable ? $request->retry(2, 200) : $request;
    }

    private function accessToken(): string
    {
        $clientId = (string) config('services.maviance.client_id');
        $cacheKey = 'payments.enkap.token.'.hash('sha256', $this->baseUrl().'|'.$clientId);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($clientId): string {
            $response = Http::asForm()
                ->withBasicAuth($clientId, (string) config('services.maviance.client_secret'))
                ->timeout(15)
                ->connectTimeout(5)
                ->post($this->baseUrl().'/token', ['grant_type' => 'client_credentials'])
                ->throw()
                ->json();
            $token = (string) ($response['access_token'] ?? '');

            if ($token === '') {
                throw new RuntimeException('e-nkap n’a pas retourné de jeton d’accès.');
            }

            return $token;
        });
    }

    private function assertConfigured(): void
    {
        if (! config('services.maviance.enabled')
            || ! config('services.maviance.client_id')
            || ! config('services.maviance.client_secret')) {
            throw new RuntimeException('Le paiement local e-nkap n’est pas configuré.');
        }
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.maviance.base_url'), '/');
    }
}
