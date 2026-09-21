<?php

namespace Tests\Unit;

use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\S3pGateway;
use App\Services\Payments\S3pTimestamp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class S3pGatewayContractTest extends TestCase
{
    private Subscription $sub;
    private array $state;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 16)->startOfDay()->addHours(12));
        config(['payments.s3p.enabled' => true, 'payments.s3p.simulation' => false,
            'payments.s3p.public_key' => 'contract-public', 'payments.s3p.secret_key' => 'contract-secret',
            'payments.s3p.webhook_secret' => 'test-callback', 'payments.s3p.timestamp_timezone' => null,
            'payments.s3p.services.orange_money' => '123', 'payments.s3p.merchants.orange_money' => 'TEST-OM']);
        Cache::flush();
        Http::preventStrayRequests();
        $this->sub = new Subscription;
        $this->sub->forceFill(['mobile_provider' => 'orange_money', 'montant_total' => 75750, 'payment_phone' => '237699000001',
            's3p_reference' => 'PEK-contract', 's3p_quote_id' => 'QUOTE-1', 's3p_pay_item' => 'COLLECTION-1',
            's3p_ptn' => 'PTN-1', 's3p_quote_expires_at' => now()->addMinutes(2),
            's3p_context' => ['base_url' => config('payments.s3p.base_url'), 'account_hash' => hash('sha256', 'contract-public'),
                'merchant' => 'TEST-OM', 'serviceid' => '123', 'wallet_format' => 'international', 'amount' => '75750.00', 'currency' => 'XAF']]);
        $this->sub->setRelation('user', new User(['first_name' => 'Contract', 'last_name' => 'Test', 'email' => 'contract@example.test']));
        $this->state = ['trid' => 'PEK-contract', 'ptn' => 'PTN-1', 'payItemId' => 'COLLECTION-1', 'merchant' => 'TEST-OM',
            'serviceid' => '123', 'priceLocalCur' => '75750.00', 'localCur' => 'XAF', 'priceSystemCur' => 75750,
            'systemCur' => 'XAF', 'timestamp' => now()->toIso8601String(), 'status' => 'SUCCESS', 'errorCode' => 0,
            'receiptNumber' => 'RECEIPT-1', 'veriCode' => 'CODE-1'];
    }

    private function fake(array $state = [], array $quote = [], array $catalog = []): void
    {
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'contract-token', 'token_type' => 'Bearer', 'expires_in' => 120]),
            '*/verifytx*' => Http::response(array_replace($this->state, $state)),
            '*/cashout*' => Http::response([array_replace(['payItemId' => 'COLLECTION-1', 'merchant' => 'TEST-OM', 'serviceid' => '123', 'localCur' => 'XAF', 'amountType' => 'CUSTOM'], $catalog)]),
            '*/quotestd' => Http::response(array_replace(['quoteId' => 'QUOTE-1', 'payItemId' => 'COLLECTION-1', 'priceLocalCur' => 75750, 'amountLocalCur' => 75750, 'localCur' => 'XAF', 'expiresIn' => 120], $quote)),
            '*/collectstd' => Http::response(['ptn' => 'PTN-1', 'trid' => 'PEK-contract', 'status' => 'PENDING']),
        ]);
    }

    public static function invalidTransactions(): array
    {
        return [
            'wrong reference' => [['trid' => 'someone-else']], 'wrong ptn' => [['ptn' => 'OTHER']],
            'wrong service' => [['serviceid' => '124']], 'wrong merchant' => [['merchant' => 'OTHER']],
            'wrong item' => [['payItemId' => 'PAYOUT-1']], 'wrong amount' => [['priceLocalCur' => 1]],
            'fractional amount' => [['priceLocalCur' => '75750.00000001']], 'scientific notation' => [['priceLocalCur' => '7.575e4']],
            'boolean amount' => [['priceLocalCur' => true]], 'wrong currency' => [['localCur' => 'EUR']],
            'wrong system amount' => [['priceSystemCur' => 1]], 'wrong system currency' => [['systemCur' => 'USD']],
            'wrong wallet' => [['serviceNumber' => '237677000002']], 'status mismatch' => [['errorCode' => 703202]],
            'unknown status' => [['status' => 'PAID']], 'structured ptn' => [['ptn' => ['PTN-1']]],
        ];
    }

    #[DataProvider('invalidTransactions')]
    public function test_mismatching_transaction_is_rejected(array $changes): void
    {
        $this->fake($changes);
        $this->expectException(\RuntimeException::class);
        app(S3pGateway::class)->verify($this->sub);
    }

    public function test_matching_transaction_is_accepted_without_any_collection_request(): void
    {
        $this->fake();
        $this->assertSame('RECEIPT-1', app(S3pGateway::class)->verify($this->sub)['receiptNumber']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/collectstd'));
    }

    public function test_documented_optional_pay_item_may_be_absent_when_identity_and_service_match(): void
    {
        unset($this->state['payItemId']);
        $this->fake();
        $this->assertSame('PTN-1', app(S3pGateway::class)->verify($this->sub)['ptn']);
    }

    public function test_staging_success_with_null_error_code_is_supported(): void
    {
        $this->fake(['errorCode' => null]);
        $this->assertSame('SUCCESS', app(S3pGateway::class)->verify($this->sub)['status']);
    }

    public function test_staging_pending_with_null_error_code_remains_pending(): void
    {
        $this->fake(['errorCode' => null, 'status' => 'PENDING']);
        $this->assertSame('PENDING', app(S3pGateway::class)->verify($this->sub)['status']);
    }

    public function test_empty_optional_receipt_fields_are_normalized_without_weakening_ptn_check(): void
    {
        $this->fake(['receiptNumber' => '', 'veriCode' => '']);
        $result = app(S3pGateway::class)->verify($this->sub);
        $this->assertNull($result['receiptNumber']);
        $this->assertNull($result['veriCode']);
        $this->assertSame('PTN-1', $result['ptn']);
    }

    public function test_collection_is_never_replayed_after_authentication_failure(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 120]),
            '*/collectstd' => Http::response(['respCode' => 4009], 401),
        ]);
        try { app(S3pGateway::class)->collect($this->sub); $this->fail('Expected provider refusal'); }
        catch (\Illuminate\Http\Client\RequestException) {
            $this->assertCount(1, Http::recorded(fn ($r) => str_contains($r->url(), '/collectstd')));
            Http::assertSentCount(2);
        }
    }

    public function test_catalog_rejects_wrong_merchant_before_quoting(): void
    {
        $this->fake(catalog: ['merchant' => 'PAYOUT']);
        try { app(S3pGateway::class)->quote($this->sub); $this->fail('Wrong merchant accepted'); }
        catch (\RuntimeException) { Http::assertNotSent(fn ($r) => str_contains($r->url(), '/quotestd')); }
    }

    public function test_catalog_is_filtered_locally_when_provider_ignores_query_filters(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 120]),
            '*/cashout*' => Http::response([
                ['payItemId' => 'OTHER', 'merchant' => 'OTHER', 'serviceid' => '456', 'localCur' => 'XAF', 'amountType' => 'CUSTOM'],
                ['payItemId' => 'COLLECTION-1', 'merchant' => 'TEST-OM', 'serviceid' => '123', 'localCur' => 'XAF', 'amountType' => 'CUSTOM'],
            ]),
            '*/quotestd' => Http::response(['quoteId' => 'QUOTE-1', 'payItemId' => 'COLLECTION-1', 'priceLocalCur' => '75750.00', 'localCur' => 'XAF', 'expiresAt' => now()->addMinutes(2)->toIso8601String()]),
        ]);
        $this->assertSame('COLLECTION-1', app(S3pGateway::class)->quote($this->sub)['item']);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/quotestd') && $r['payItemId'] === 'COLLECTION-1');
    }

    public function test_quote_with_added_provider_fees_is_rejected_before_debit(): void
    {
        $this->fake(quote: ['priceLocalCur' => 76000]);
        $this->expectException(\RuntimeException::class);
        app(S3pGateway::class)->quote($this->sub);
    }

    public function test_expired_quote_cannot_be_collected(): void
    {
        $this->fake();
        $this->sub->s3p_quote_expires_at = now()->subSecond();
        try { app(S3pGateway::class)->collect($this->sub); $this->fail('Expired quote accepted'); }
        catch (\RuntimeException) { Http::assertNothingSent(); }
    }

    public function test_national_wallet_format_keeps_compliance_phone_international(): void
    {
        $this->fake();
        $this->sub->s3p_context = [...$this->sub->s3p_context, 'wallet_format' => 'national'];
        app(S3pGateway::class)->collect($this->sub);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/collectstd') && $r['serviceNumber'] === '699000001' && $r['customerPhonenumber'] === '237699000001');
    }

    public function test_changed_account_cannot_verify_old_payment(): void
    {
        $this->fake();
        config(['payments.s3p.public_key' => 'another-account']);
        try { app(S3pGateway::class)->verify($this->sub); $this->fail('Wrong account accepted'); }
        catch (\RuntimeException) { Http::assertNothingSent(); }
    }

    public function test_unsafe_host_is_rejected_before_credentials_are_sent(): void
    {
        $this->fake();
        config(['payments.s3p.base_url' => 'https://attacker.invalid']);
        try { app(S3pGateway::class)->quote($this->sub); $this->fail('Unsafe host accepted'); }
        catch (\RuntimeException) { Http::assertNothingSent(); }
    }

    public function test_authentication_refresh_retries_read_only_once(): void
    {
        Http::fake([
            '*/oauth/token' => Http::sequence()->push(['access_token' => 'old-token', 'expires_in' => 120])->push(['access_token' => 'new-token', 'expires_in' => 120]),
            '*/verifytx*' => Http::sequence()->push(['respCode' => 4009], 401)->push($this->state),
        ]);
        $this->assertSame('SUCCESS', app(S3pGateway::class)->verify($this->sub)['status']);
        Http::assertSentCount(4);
    }

    public function test_legacy_timestamp_requires_explicit_timezone_and_preserves_value_date(): void
    {
        config(['payments.s3p.timestamp_timezone' => 'UTC']);
        $this->assertSame('2026-09-17', S3pTimestamp::parse('2026-09-16 23:30:00')->timezone('Africa/Douala')->toDateString());
        config(['payments.s3p.timestamp_timezone' => null]);
        $this->expectException(\RuntimeException::class);
        S3pTimestamp::parse('2026-09-16 23:30:00');
    }

    public function test_invalid_calendar_date_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        S3pTimestamp::parse('2026-02-30T12:00:00+00:00');
    }
}
