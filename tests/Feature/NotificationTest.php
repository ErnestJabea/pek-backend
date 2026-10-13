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
            ->postJson('/api/notifications/read-all', [], ['Accept-Language' => 'fr']);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Toutes les notifications ont été marquées comme lues.');

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->user->id,
            'read_at' => null,
        ]);
    }

    public function test_notifications_are_translated_into_english(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'title' => 'Demande de mise à jour transmise',
            'body' => 'Votre demande de modification (Personne Physique - Salarié secteur privé) a bien été transmise à l\'équipe Kori et est en attente de validation.',
            'type' => 'info',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/notifications', ['Accept-Language' => 'en']);

        $response->assertStatus(200);
        $notif = $response->json('data.0');
        $this->assertSame('Update request submitted', $notif['title']);
        $this->assertSame('Your update request (Individual - Private sector employee) has been successfully submitted to the Kori team and is pending validation.', $notif['body']);
    }
}
