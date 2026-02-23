<?php

namespace Tests\Feature;

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

    public function test_can_get_room_history(): void
    {
        $room = Room::create(['name' => 'Test']);
        $response = $this->getJson("/api/rooms/{$room->id}/history");

        $response->assertStatus(200)
            ->assertExactJson([]);
    }
}
