<?php

namespace Tests\Feature;

use App\Models\Analytic;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension required.');
        }
        parent::setUp();
    }

    public function test_can_get_analytics_for_room_without_analytic_record(): void
    {
        $room = Room::create(['name' => 'Test']);

        $response = $this->getJson("/api/analytics/room/{$room->id}");

        $response->assertStatus(200)
            ->assertJsonPath('room_id', $room->id)
            ->assertJsonPath('room_name', 'Test')
            ->assertJsonPath('message_count', 0);
    }

    public function test_can_get_analytics_for_room_with_analytic_record(): void
    {
        $room = Room::create(['name' => 'Test']);
        Analytic::create(['room_id' => $room->id, 'message_count' => 42]);

        $response = $this->getJson("/api/analytics/room/{$room->id}");

        $response->assertStatus(200)
            ->assertJsonPath('room_id', $room->id)
            ->assertJsonPath('room_name', 'Test')
            ->assertJsonPath('message_count', 42);
    }

    public function test_analytics_returns_404_for_missing_room(): void
    {
        $response = $this->getJson('/api/analytics/room/99999');

        $response->assertStatus(404);
    }
}
