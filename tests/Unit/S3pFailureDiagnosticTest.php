<?php

namespace Tests\Unit;

use App\Services\Payments\S3pFailureDiagnostic;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\TestCase;

class S3pFailureDiagnosticTest extends TestCase
{
    public function test_http_failure_retains_only_status_and_numeric_code(): void
    {
        $response = new Response(new PsrResponse(400, [], json_encode([
            'respCode' => 4204, 'devMsg' => 'SECRET-TEST 237699000001', 'token' => 'SECRET-TEST',
        ])));
        $data = S3pFailureDiagnostic::from(new RequestException($response));
        $this->assertSame(400, $data['http_status']);
        $this->assertSame('4204', $data['provider_code']);
        $this->assertStringNotContainsString('SECRET-TEST', json_encode($data));
        $this->assertStringNotContainsString('237699000001', json_encode($data));
    }

    public function test_untrusted_code_and_connection_message_are_not_logged(): void
    {
        $response = new Response(new PsrResponse(400, [], '{"respCode":"SECRET-TEST"}'));
        $this->assertArrayNotHasKey('provider_code', S3pFailureDiagnostic::from(new RequestException($response)));
        $data = S3pFailureDiagnostic::from(new ConnectionException('SECRET-TEST'));
        $this->assertSame('connection_error', $data['reason']);
        $this->assertStringNotContainsString('SECRET-TEST', json_encode($data));
    }
}
