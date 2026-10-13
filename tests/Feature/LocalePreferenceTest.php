<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalePreferenceTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => uniqid('locale_') . '@example.test',
            'password' => 'ValidPassword123!',
            'locale' => 'fr',
            'has_temp_password' => false,
        ], $overrides));
    }

    public function test_user_can_update_locale_preference(): void
    {
        $user = $this->createUser(['locale' => 'fr']);

        $response = $this->actingAs($user)
            ->postJson('/api/user/locale', [
                'locale' => 'en',
            ]);

        $response->assertOk()
            ->assertJsonPath('locale', 'en');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'locale' => 'en',
        ]);
    }

    public function test_user_cannot_set_unsupported_locale(): void
    {
        $user = $this->createUser(['locale' => 'fr']);

        $response = $this->actingAs($user)
            ->postJson('/api/user/locale', [
                'locale' => 'es',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['locale']);
    }

    public function test_accept_language_header_sets_application_locale(): void
    {
        $user = $this->createUser(['locale' => 'fr']);

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->getJson('/api/user');

        $this->assertEquals('en', app()->getLocale());
    }
}
