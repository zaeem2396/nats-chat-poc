<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension required.');
        }
        parent::setUp();
    }

    public function test_can_list_rooms_empty(): void
    {
        $response = $this->getJson('/api/rooms');

        $response->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_can_list_rooms_with_rooms(): void
    {
        Room::create(['name' => 'First']);
        Room::create(['name' => 'Second']);

        $response = $this->getJson('/api/rooms');

        $response->assertStatus(200)->assertJsonCount(2);
        $names = array_column($response->json(), 'name');
        $this->assertContains('First', $names);
        $this->assertContains('Second', $names);
    }

    public function test_can_create_room(): void
    {
        $response = $this->postJson('/api/rooms', ['name' => 'General']);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'General')
            ->assertJsonStructure(['id', 'name', 'created_at', 'updated_at']);
        $this->assertDatabaseHas('rooms', ['name' => 'General']);
    }

    public function test_create_room_requires_name(): void
    {
        $response = $this->postJson('/api/rooms', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_get_room_history_empty(): void
    {
        $room = Room::create(['name' => 'Test']);
        $response = $this->getJson("/api/rooms/{$room->id}/history");

        $response->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_can_get_room_history_with_messages(): void
    {
        $room = Room::create(['name' => 'Test']);
        Message::create([
            'room_id' => $room->id,
            'user_id' => 1,
            'content' => 'Hello',
            'message_id' => '00000000-0000-0000-0000-000000000001',
            'timestamp' => now(),
        ]);

        $response = $this->getJson("/api/rooms/{$room->id}/history");

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.content', 'Hello')
            ->assertJsonPath('0.user_id', 1);
    }

    public function test_room_history_returns_404_for_missing_room(): void
    {
        $response = $this->getJson('/api/rooms/99999/history');

        $response->assertStatus(404);
    }
}
