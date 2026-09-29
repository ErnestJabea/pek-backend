<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemporaryPasswordSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_password_blocks_sensitive_routes_in_both_api_versions(): void
    {
        $user = User::create(['first_name' => 'Test', 'last_name' => 'Temporary',
            'email' => 'temporary@example.test', 'password' => 'Temporary-only-2026!']);
        $user->forceFill(['has_temp_password' => true])->save();
        $this->actingAs($user);
        foreach (['/api', '/api/v1'] as $prefix) {
            $this->getJson($prefix.'/user')->assertOk();
            $this->postJson($prefix.'/subscriptions', [])->assertForbidden()
                ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
            $this->postJson($prefix.'/update-profile', [])->assertForbidden();
            $this->postJson($prefix.'/reset-temp-password', [])->assertUnprocessable();
        }
        $this->assertDatabaseCount('subscriptions', 0);
        $user->forceFill(['has_temp_password' => false])->save();
        $this->getJson('/api/v1/subscriptions')->assertOk();
    }
}
