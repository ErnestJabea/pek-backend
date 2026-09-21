<?php

namespace App\Services\IdentityVerification;

use App\Models\IdentityVerification;
use App\Models\OnboardingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IdenfyIdentityVerificationProvider implements IdentityVerificationProvider
{
    public function createSession(IdentityVerification $verification, OnboardingSession $session): array
    {
        $apiKey = (string) config('services.idenfy.api_key');
        $apiSecret = (string) config('services.idenfy.api_secret');

        if ($apiKey === '' || $apiSecret === '') {
            throw new RuntimeException('Le fournisseur de vérification d’identité n’est pas configuré.');
        }

        $payload = $session->payload;
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $lifetime = max(300, min(2592000, (int) config('identity_verification.session_lifetime')));
        $sessionLength = max(60, min(3600, (int) config('identity_verification.session_length')));

        $requestData = [
            'clientId' => $verification->client_reference,
            'externalRef' => $verification->id,
            'tokenType' => 'IDENTIFICATION',
            'generateDigitString' => false,
            'locale' => 'fr',
            'expiryTime' => $lifetime,
            'sessionLength' => $sessionLength,
            'firstName' => $payload['prenom'],
            'lastName' => $payload['nom'],
            'dateOfBirth' => $payload['dob'],
            'documentNumber' => $payload['num_piece'],
            'dateOfExpiry' => $payload['expiration_piece'],
            'address' => $payload['adresse'],
            'sex' => ($payload['civ'] ?? null) === 'Mme' ? 'F' : 'M',
            'successUrl' => $frontendUrl.'/onboarding?identity=returned',
            'errorUrl' => $frontendUrl.'/onboarding?identity=failed',
            'unverifiedUrl' => $frontendUrl.'/onboarding?identity=pending',
            'showInstructions' => true,
            'reviewSuccessful' => true,
            'reviewFailed' => true,
            'checkLiveness' => true,
            'checkDuplicateFaces' => true,
            'checkDuplicatePersonalData' => true,
            'checkIpProxy' => true,
        ];

        $country = (string) config('services.idenfy.country');
        $documents = config('services.idenfy.documents', []);
        $callbackUrl = (string) config('services.idenfy.callback_url');
        if ($country !== '') {
            $requestData['country'] = $country;
        }
        if ($documents !== []) {
            $requestData['documents'] = $documents;
        }
        if ($callbackUrl !== '') {
            $requestData['callbackUrl'] = $callbackUrl;
        }

        $response = Http::withBasicAuth($apiKey, $apiSecret)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post(rtrim((string) config('services.idenfy.base_url'), '/').'/api/v2/token', $requestData);

        if (! $response->successful()) {
            throw new RuntimeException('Le fournisseur de vérification d’identité a refusé la création de session.');
        }

        $providerReference = (string) ($response->json('scanRef') ?? '');
        $sessionUrl = (string) (
            $response->json('sessionUrl')
            ?? $response->json('redirectUrl')
            ?? ''
        );

        if ($sessionUrl === '' && ($token = $response->json('tokenString') ?? $response->json('authToken'))) {
            $sessionUrl = rtrim((string) config('services.idenfy.base_url'), '/').'/api/v2/redirect?authToken='.urlencode($token);
        }

        if ($providerReference === '' || ! $this->isAllowedSessionUrl($sessionUrl)) {
            throw new RuntimeException('La réponse du fournisseur de vérification est incomplète.');
        }

        $expiration = $response->json('expiration');

        return [
            'provider_reference' => $providerReference,
            'session_url' => $sessionUrl,
            'expires_at' => $expiration
                ? CarbonImmutable::parse($expiration)
                : now()->addSeconds($lifetime),
        ];
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('services.idenfy.webhook_signing_secret');

        if ($secret === '' || ! is_string($signature) || ! preg_match('/^[a-f0-9]{64}$/i', $signature)) {
            return false;
        }

        return hash_equals(
            hash_hmac('sha256', $rawBody, $secret),
            strtolower($signature)
        );
    }

    private function isAllowedSessionUrl(string $sessionUrl): bool
    {
        $scheme = parse_url($sessionUrl, PHP_URL_SCHEME);
        $host = strtolower((string) parse_url($sessionUrl, PHP_URL_HOST));
        $allowedHosts = config('services.idenfy.allowed_redirect_hosts', []);

        return $scheme === 'https' && in_array($host, $allowedHosts, true);
    }
}
