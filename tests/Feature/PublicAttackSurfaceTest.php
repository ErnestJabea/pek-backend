<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicAttackSurfaceTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('removedOperationalRouteProvider')]
    public function test_operational_endpoints_are_not_publicly_routable(string $method, string $path): void
    {
        $this->json($method, $path)->assertNotFound();
    }

    public static function removedOperationalRouteProvider(): array
    {
        return [
            ['POST', '/api/run-migrations'],
            ['GET', '/api/clear-cache'],
            ['GET', '/api/diagnose-queue'],
            ['GET', '/api/run-queue'],
        ];
    }

    public function test_identity_status_and_session_require_authentication(): void
    {
        $this->getJson('/api/identity-verification/status')->assertUnauthorized();
        $this->postJson('/api/identity-verification/session')->assertUnauthorized();
    }
}
