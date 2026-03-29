<?php

namespace Tests\Feature;

use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension required.');
        }
        parent::setUp();
        $this->app['config']->set('queue.default', 'sync');
    }

    public function test_can_send_message(): void
    {
        $room = Room::create(['name' => 'Test']);
        $response = $this->postJson("/api/rooms/{$room->id}/message", [
            'user_id' => 1,
            'content' => 'Hello',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('content', 'Hello')
            ->assertJsonPath('user_id', 1);
        $this->assertDatabaseHas('messages', ['room_id' => $room->id, 'content' => 'Hello']);
    }

    public function test_send_message_requires_user_id_and_content(): void
    {
        $room = Room::create(['name' => 'Test']);
        $response = $this->postJson("/api/rooms/{$room->id}/message", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'content']);
    }

    public function test_send_message_rejects_invalid_user_id(): void
    {
        $room = Room::create(['name' => 'Test']);
        $response = $this->postJson("/api/rooms/{$room->id}/message", [
            'user_id' => 0,
            'content' => 'Hello',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_send_message_returns_404_for_missing_room(): void
    {
        $response = $this->postJson('/api/rooms/99999/message', [
            'user_id' => 1,
            'content' => 'Hello',
        ]);

        $response->assertStatus(404);
    }
}
