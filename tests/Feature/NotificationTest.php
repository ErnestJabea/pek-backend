<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_can_fetch_notifications(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'title' => 'Test Notification',
            'body' => 'This is a test body',
            'type' => 'test',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/notifications');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('per_page', 30);
        $response->assertJsonFragment([
            'title' => 'Test Notification',
            'body' => 'This is a test body',
        ]);
    }

    public function test_can_mark_all_notifications_as_read(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'title' => 'Unread Notification',
            'body' => 'Should be read soon',
            'type' => 'test',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/notifications/read-all');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Toutes les notifications ont été marquées comme lues.');

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->user->id,
            'read_at' => null,
        ]);
    }
}
