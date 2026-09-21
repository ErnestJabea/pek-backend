<?php

namespace App\Services\Payments;

use App\Models\Subscription;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class S3pGateway
{
    public function isStaging(): bool
    {
        return parse_url((string) config('payments.s3p.base_url'), PHP_URL_HOST) === 's3p.smobilpay.staging.maviance.info';
    }

    public static function mustKeepTestFundsSeparate(Subscription $sub): bool
    {
        $staging = parse_url((string) ($sub->s3p_context['base_url'] ?? ''), PHP_URL_HOST) === 's3p.smobilpay.staging.maviance.info';
        $isolatedTest = app()->runningUnitTests() && config('database.default') === 'sqlite'
            && config('database.connections.sqlite.database') === ':memory:';

        return $staging && ! $isolatedTest;
    }

    public function isSimulation(): bool
    {
        // Fake money must never enter a persistent client portfolio.
        return (bool) config('payments.s3p.simulation') && app()->runningUnitTests()
            && config('database.default') === 'sqlite'
            && config('database.connections.sqlite.database') === ':memory:';
    }

    public function available(string $operator): bool
    {
        if (! in_array($operator, ['orange_money', 'mtn_momo'], true)) {
            return false;
        }
        if (app()->environment('production') && $this->isStaging()) {
            return false;
        }
        if (config('payments.s3p.simulation') && ! $this->isSimulation()) {
            return false;
        }
        if ($this->isSimulation() && in_array($operator, ['orange_money', 'mtn_momo'], true)) {
            return true;
        }

        return (bool) (config('payments.s3p.enabled') && config('payments.s3p.public_key')
            && config('payments.s3p.secret_key') && config('payments.s3p.webhook_secret')
            && config('payments.s3p.services.'.$operator) && config('payments.s3p.merchants.'.$operator));
    }

    private function base(): string
    {
        $base = rtrim((string) config('payments.s3p.base_url'), '/');
        $parts = parse_url($base);
        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443) || ! in_array($parts['path'] ?? '', ['', '/'], true)
            || ! in_array($parts['host'] ?? '', config('payments.s3p.allowed_hosts'), true)) {
            throw new RuntimeException('Hôte S3P non autorisé.');
        }

        return $base;
    }

    private function request(bool $freshToken = false)
    {
        if (config('payments.s3p.simulation')) {
            throw new RuntimeException('Simulation réservée aux tests isolés.');
        }
        if (! config('payments.s3p.enabled')) {
            throw new RuntimeException('S3P désactivé.');
        }
        $base = $this->base();
        $ca = config('payments.s3p.ca_bundle') ?: true;
        if ($ca !== true && (! is_string($ca) || ! is_file($ca) || ! is_readable($ca))) {
            throw new RuntimeException('Autorités TLS indisponibles.');
        }
        $cacheKey = 's3p.token.'.hash('sha256', $base.'|'.config('payments.s3p.public_key').'|'.config('payments.s3p.secret_key'));
        if ($freshToken) {
            Cache::forget($cacheKey);
        }
        $token = Cache::get($cacheKey);
        if (! $token) {
            $data = Http::asForm()->withBasicAuth((string) config('payments.s3p.public_key'), (string) config('payments.s3p.secret_key'))
                ->withOptions(['allow_redirects' => false, 'verify' => $ca])->connectTimeout(5)->timeout(15)
                ->post($base.'/oauth/token', ['grant_type' => 'client_credentials'])->throw()->json();
            $token = $data['access_token'] ?? null;
            if (! is_string($token) || $token === '' || preg_match('/\s/', $token)
                || ! is_int($data['expires_in'] ?? null) || $data['expires_in'] <= 60
                || (isset($data['token_type']) && strcasecmp($data['token_type'], 'Bearer') !== 0)) {
                throw new RuntimeException('Jeton S3P invalide.');
            }
            Cache::put($cacheKey, $token, max(1, (int) $data['expires_in'] - 60));
        }

        return Http::acceptJson()->withToken($token)->withHeaders(['x-api-version' => config('payments.s3p.api_version')])
            ->withOptions(['allow_redirects' => false, 'verify' => $ca])->connectTimeout(5)->timeout(20);
    }

    public function quote(Subscription $sub): array
    {
        if ($this->isSimulation()) {
            return [
                'id' => 'SIM-QUOTE-'.strtoupper(bin2hex(random_bytes(6))),
                'item' => 'SIM-ITEM-'.$sub->mobile_provider,
                'context' => ['simulation' => true], 'expires_at' => now()->addMinutes(2),
            ];
        }

        $service = (string) config('payments.s3p.services.'.$sub->mobile_provider);
        $merchant = (string) config('payments.s3p.merchants.'.$sub->mobile_provider);
        if (! $this->available($sub->mobile_provider)) {
            throw new RuntimeException('Opérateur non configuré.');
        }
        $catalog = $this->read('cashout', ['serviceid' => $service, 'merchant' => $merchant]);
        if (! is_array($catalog) || ! array_is_list($catalog)) {
            throw new RuntimeException('Catalogue S3P invalide.');
        }
        // Some staging versions ignore the URL filters: select and verify locally as well.
        $catalog = array_values(array_filter($catalog, fn ($row) => is_array($row)
            && (string) ($row['serviceid'] ?? '') === $service && ($row['merchant'] ?? null) === $merchant));
        if (count($catalog) !== 1 || empty($catalog[0]['payItemId'])) {
            throw new RuntimeException('Le service doit désigner un seul produit d’encaissement.');
        }
        $item = $catalog[0]['payItemId'];
        if (! is_string($item) || strlen($item) > 255 || (string) ($catalog[0]['serviceid'] ?? '') !== $service
            || ($catalog[0]['merchant'] ?? null) !== $merchant || ($catalog[0]['localCur'] ?? null) !== 'XAF'
            || ($catalog[0]['amountType'] ?? null) !== 'CUSTOM') {
            throw new RuntimeException('Produit d’encaissement incompatible.');
        }
        $quote = $this->request()->post($this->base().'/v2/quotestd', ['payItemId' => $item, 'amount' => (int) $sub->montant_total])->throw()->json();
        if (! is_array($quote) || ! $this->validId($quote['quoteId'] ?? null) || ($quote['payItemId'] ?? null) !== $item
            || ! $this->matchesAmount($sub, $quote)
            || (isset($quote['amountLocalCur']) && ! $this->sameMoney($quote['amountLocalCur'], $sub->montant_total))) {
            throw new RuntimeException('Le devis S3P ne correspond pas au total confirmé.');
        }
        $expiry = isset($quote['expiresAt']) ? S3pTimestamp::parse($quote['expiresAt'])
            : ((is_int($quote['expiresIn'] ?? null) && $quote['expiresIn'] > 0 && $quote['expiresIn'] <= 86400) ? now()->addSeconds($quote['expiresIn']) : null);
        if (! $expiry || $expiry->lte(now()->addSeconds(5))) {
            throw new RuntimeException('Devis expiré ou expiration absente.');
        }
        $format = config('payments.s3p.wallet_formats.'.$sub->mobile_provider);
        if (! in_array($format, ['national', 'international'], true)) {
            throw new RuntimeException('Format de portefeuille non configuré.');
        }

        return ['id' => $quote['quoteId'], 'item' => $item, 'expires_at' => $expiry,
            'context' => ['base_url' => $this->base(), 'account_hash' => hash('sha256', (string) config('payments.s3p.public_key')),
                'merchant' => $merchant, 'serviceid' => $service, 'wallet_format' => $format,
                'amount' => (string) $sub->montant_total, 'currency' => 'XAF']];
    }

    public function collect(Subscription $sub): array
    {
        if ($this->isSimulation()) {
            return [
                'ptn' => 'SIM-PTN-'.strtoupper(bin2hex(random_bytes(6))),
                'status' => 'PENDING',
            ];
        }

        // Deliberately no retry: a timeout may hide an accepted debit.
        $this->assertContext($sub);
        if (! $sub->s3p_quote_expires_at || $sub->s3p_quote_expires_at->lte(now()->addSeconds(2))) {
            throw new RuntimeException('Devis expiré avant exécution.');
        }
        $data = $this->request()->post($this->base().'/v2/collectstd', [
            'quoteId' => $sub->s3p_quote_id, 'trid' => $sub->s3p_reference,
            'serviceNumber' => $this->walletNumber($sub), 'customerPhonenumber' => $sub->payment_phone,
            'customerName' => trim($sub->user->first_name.' '.$sub->user->last_name),
            'customerEmailaddress' => $sub->user->email,
        ])->throw()->json();
        if (! is_array($data) || ! $this->validId($data['ptn'] ?? null)
            || (isset($data['trid']) && $data['trid'] !== $sub->s3p_reference)
            || (isset($data['payItemId']) && $data['payItemId'] !== $sub->s3p_pay_item)
            || (isset($data['priceLocalCur']) && ! $this->matchesAmount($sub, $data))) {
            throw new RuntimeException('Réponse d’encaissement incohérente. Vérification obligatoire.');
        }

        return $data;
    }

    public function verify(Subscription $sub): array
    {
        $simulated = str_starts_with((string) $sub->s3p_quote_id, 'SIM-QUOTE-');
        if ($simulated !== $this->isSimulation()) {
            throw new RuntimeException('Transaction et environnement de paiement incompatibles.');
        }
        if ($this->isSimulation()) {
            return [
                'trid' => $sub->s3p_reference,
                'ptn' => $sub->s3p_ptn ?: 'SIM-PTN-'.strtoupper(bin2hex(random_bytes(6))),
                'payItemId' => $sub->s3p_pay_item ?: 'SIM-ITEM-'.$sub->mobile_provider,
                'status' => 'SUCCESS',
                'timestamp' => now()->toIso8601String(),
                'priceLocalCur' => (float) $sub->montant_total,
                'localCur' => 'XAF',
            ];
        }

        $this->assertContext($sub);
        $data = $this->read('verifytx', ['trid' => $sub->s3p_reference]);
        if (is_array($data) && array_is_list($data)) {
            if (count($data) !== 1) {
                throw new RuntimeException('Résultat S3P ambigu.');
            }
            $data = $data[0];
        }
        if (! is_array($data) || ($data['trid'] ?? null) !== $sub->s3p_reference
            || ! $this->validId($data['ptn'] ?? null) || ($sub->s3p_ptn && $sub->s3p_ptn !== $data['ptn'])
            || (isset($data['payItemId']) && $data['payItemId'] !== $sub->s3p_pay_item) || ! $this->matchesAmount($sub, $data)
            || (string) ($data['serviceid'] ?? '') !== $sub->s3p_context['serviceid']
            || ($data['merchant'] ?? null) !== $sub->s3p_context['merchant']
            || (isset($data['serviceNumber']) && (string) $data['serviceNumber'] !== $this->walletNumber($sub))) {
            throw new RuntimeException('Transaction S3P incohérente.');
        }
        $status = $data['status'] ?? null;
        $error = $data['errorCode'] ?? null;
        // Staging v3.0.0 returns null, including on SUCCESS; a missing code is not an error.
        if (! in_array($status, ['PENDING', 'SUCCESS', 'ERRORED', 'REVERSED'], true)
            || ($error !== null && ((! is_int($error) && ! is_string($error) && ! is_float($error)) || ! preg_match('/^\d{1,10}$/D', (string) $error)))
            || ($error !== null && in_array($status, ['PENDING', 'SUCCESS'], true) && (int) $error !== 0)
            || ($error !== null && $status === 'ERRORED' && (int) $error === 0)
            || ($error !== null && $status === 'REVERSED' && (int) $error !== 3)) {
            throw new RuntimeException('Statut et code fournisseur incohérents.');
        }
        foreach (['receiptNumber', 'veriCode'] as $field) {
            if (($data[$field] ?? null) === '') {
                $data[$field] = null;
            }
            if (isset($data[$field]) && (! is_string($data[$field]) || mb_strlen($data[$field]) > 100
                || preg_match('/[\x00-\x1F\x7F]/', $data[$field]))) {
                throw new RuntimeException('Détail de transaction invalide.');
            }
        }
        if (isset($data['timestamp']) && (! is_string($data['timestamp']) || strlen($data['timestamp']) > 64)) {
            throw new RuntimeException('Horodatage fournisseur invalide.');
        }

        return $data;
    }

    private function matchesAmount(Subscription $sub, array $data): bool
    {
        $amount = $data['priceLocalCur'] ?? null;

        return ($data['localCur'] ?? null) === 'XAF' && $this->sameMoney($amount, $sub->montant_total)
            && (! isset($data['systemCur']) || $data['systemCur'] === 'XAF')
            && (! isset($data['priceSystemCur']) || $this->sameMoney($data['priceSystemCur'], $sub->montant_total));
    }

    private function sameMoney(mixed $amount, mixed $expected): bool
    {
        if ((! is_string($amount) && ! is_int($amount) && ! is_float($amount))
            || ! preg_match('/^\d{1,12}(?:\.\d{1,8})?$/D', (string) $amount)) {
            return false;
        }

        return BigDecimal::of((string) $amount)->isEqualTo(BigDecimal::of((string) $expected));
    }

    private function validId(mixed $id): bool
    {
        return is_string($id) && (bool) preg_match('/^[A-Za-z0-9._:@-]{1,100}$/D', $id);
    }

    private function assertContext(Subscription $sub): void
    {
        $context = $sub->s3p_context;
        if (! is_array($context) || ($context['base_url'] ?? null) !== $this->base()
            || ($context['account_hash'] ?? null) !== hash('sha256', (string) config('payments.s3p.public_key'))
            || empty($context['merchant']) || empty($context['serviceid'])
            || ! in_array($context['wallet_format'] ?? null, ['national', 'international'], true)
            || ! $this->sameMoney($context['amount'] ?? null, $sub->montant_total)) {
            throw new RuntimeException('Contexte de transaction absent ou modifié.');
        }
    }

    private function walletNumber(Subscription $sub): string
    {
        $number = (string) $sub->payment_phone;
        if (! preg_match('/^2376\d{8}$/D', $number)) {
            throw new RuntimeException('Numéro de portefeuille invalide.');
        }

        return ($sub->s3p_context['wallet_format'] ?? '') === 'national' ? substr($number, 3) : $number;
    }

    private function read(string $endpoint, array $query = []): mixed
    {
        $response = $this->request()->get($this->base().'/v2/'.$endpoint, $query);
        if ($response->status() === 401 || (string) $response->json('respCode') === '4009') {
            // One token renewal, for read-only requests only. Never replay collectstd.
            $response = $this->request(true)->get($this->base().'/v2/'.$endpoint, $query);
        }

        return $response->throw()->json();
    }

    public function inspectConnection(): array
    {
        $this->read('ping');
        $catalog = $this->read('cashout');
        if (! is_array($catalog) || ! array_is_list($catalog)) {
            throw new RuntimeException('Catalogue S3P invalide.');
        }

        return array_map(fn ($item) => array_intersect_key($item, array_flip([
            'payItemId', 'merchant', 'serviceid', 'amountType', 'localCur', 'name',
        ])), $catalog);
    }
}
