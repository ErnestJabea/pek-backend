<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CorsPreflightTest extends TestCase
{
    public function test_versioned_and_legacy_login_routes_have_distinct_names(): void
    {
        $routes = app('router')->getRoutes();
        $routes->refreshNameLookups();
        $this->assertSame('api/login', $routes->getByName('login')->uri());
        $this->assertSame('api/v1/login', $routes->getByName('api.v1.login')->uri());
        // Exercises the serialization that previously failed during deployment.
        $this->assertNotEmpty($routes->compile());
    }

    #[DataProvider('allowedOriginsProvider')]
    public function test_cors_preflight_allows_trusted_origins(string $origin): void
    {
        $response = $this->withHeaders([
            'Origin' => $origin,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, Accept, Authorization',
        ])->optionsJson('/api/login');

        $response->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public static function allowedOriginsProvider(): array
    {
        return [
            ['https://pek-v2.koriassetmanagement.com'],
            ['https://pek-api-v2.ejabbing.com'],
            ['https://pek-v2.ejabbing.com'],
            ['https://pek-mobile.ejabbing.com'],
            ['https://pek.ejabbing.com'],
            ['https://pek.koriassetmanagement.com'],
            ['http://localhost:8080'],
            ['http://localhost:5173'],
            ['http://localhost:5174'],
            ['http://localhost:5175'],
            ['http://127.0.0.1:8080'],
            ['http://127.0.0.1:5173'],
        ];
    }

    private function productionCors(): void
    {
        $values = ['APP_ENV' => 'production', 'CORS_ALLOWED_ORIGINS' => 'https://pek-v2.koriassetmanagement.com',
            'FRONTEND_URL' => 'https://pek-v2.koriassetmanagement.com'];
        $previous = [];
        try {
            foreach ($values as $key => $value) {
                $previous[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
                $_ENV[$key] = $_SERVER[$key] = $value;
                putenv($key.'='.$value);
            }
            config(['cors' => require base_path('config/cors.php')]);
        } finally {
            foreach ($previous as $key => [$envValue, $serverValue, $processValue]) {
                if ($envValue === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $envValue;
                }
                if ($serverValue === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $serverValue;
                }
                putenv($processValue === false ? $key : $key.'='.$processValue);
            }
        }
    }

    public function test_production_subscription_preflight_allows_idempotency_header(): void
    {
        $this->productionCors();
        $response = $this->withHeaders([
            'Origin' => 'https://pek-v2.koriassetmanagement.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type,idempotency-key,authorization',
        ])->optionsJson('/api/subscriptions');
        $response->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'https://pek-v2.koriassetmanagement.com')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
        $this->assertStringContainsString('idempotency-key', strtolower($response->headers->get('Access-Control-Allow-Headers')));
    }

    #[DataProvider('untrustedOrigins')]
    public function test_production_does_not_trust_sibling_domains_or_lan(string $origin): void
    {
        $this->productionCors();
        $response = $this->withHeaders(['Origin' => $origin, 'Access-Control-Request-Method' => 'POST'])
            ->optionsJson('/api/login');
        // A single configured origin may be emitted statically by Fruitcake;
        // the browser still rejects it when it does not match the caller.
        $this->assertNotContains($response->headers->get('Access-Control-Allow-Origin'), [$origin, '*']);
    }

    public static function untrustedOrigins(): array
    {
        return array_map(fn ($origin) => [$origin], [
            'https://evil.e-jabbing.com', 'https://pek-v2.koriassetmanagement.com.evil.test',
            'https://evil.ejabbing.com', 'http://pek-v2.e-jabbing.com',
            'http://localhost:5173', 'http://192.168.1.2:5173', 'null',
        ]);
    }

    public function test_actual_error_response_keeps_cors_headers(): void
    {
        $this->productionCors();
        // A missing API route requires no database or credentials, but exercises the HTTP kernel.
        $this->withHeader('Origin', 'https://pek-v2.koriassetmanagement.com')->getJson('/api/nonexistent-cors-check')
            ->assertNotFound()->assertHeader('Access-Control-Allow-Origin', 'https://pek-v2.koriassetmanagement.com');
    }
}
