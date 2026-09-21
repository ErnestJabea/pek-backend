<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CorsPreflightTest extends TestCase
{
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
}
