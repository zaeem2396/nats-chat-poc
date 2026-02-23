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

    public function test_can_schedule_message(): void
    {
        $room = Room::create(['name' => 'Test']);
        $response = $this->postJson("/api/rooms/{$room->id}/schedule", [
            'user_id' => 1,
            'content' => 'Later',
            'delay_minutes' => 5,
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('message', 'Message scheduled')
            ->assertJsonPath('room_id', $room->id)
            ->assertJsonPath('delay_minutes', 5);
    }
}
