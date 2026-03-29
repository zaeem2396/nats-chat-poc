<?php

namespace Tests\Feature;

use App\Jobs\SendChatMessageJob;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * POST /api/rooms/{room}/schedule — delayed {@see SendChatMessageJob} dispatch.
 */
class ScheduleMessageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension required.');
        }
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_schedule_dispatches_job_with_correct_payload_and_delay(): void
    {
        Queue::fake();
        $now = Carbon::parse('2026-03-29 10:00:00', 'UTC');
        Carbon::setTestNow($now);

        $room = Room::create(['name' => 'Test']);

        $response = $this->postJson("/api/rooms/{$room->id}/schedule", [
            'user_id' => 42,
            'content' => 'Delayed hello',
            'delay_minutes' => 15,
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('message', 'Message scheduled')
            ->assertJsonPath('room_id', $room->id)
            ->assertJsonPath('delay_minutes', 15);

        Queue::assertPushed(SendChatMessageJob::class, function (SendChatMessageJob $job) use ($room, $now): bool {
            $expected = $now->copy()->addMinutes(15);

            return $job->roomId === $room->id
                && $job->userId === 42
                && $job->content === 'Delayed hello'
                && $job->delay !== null
                && $job->delay instanceof \DateTimeInterface
                && $expected->equalTo(Carbon::instance($job->delay));
        });

        Queue::assertPushed(SendChatMessageJob::class, 1);
    }

    public function test_schedule_defaults_delay_to_one_minute_when_delay_minutes_omitted(): void
    {
        Queue::fake();
        $now = Carbon::parse('2026-03-29 10:00:00', 'UTC');
        Carbon::setTestNow($now);

        $room = Room::create(['name' => 'Test']);

        $response = $this->postJson("/api/rooms/{$room->id}/schedule", [
            'user_id' => 1,
            'content' => 'Soon',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('delay_minutes', 1);

        $expected = $now->copy()->addMinute();
        Queue::assertPushed(SendChatMessageJob::class, function (SendChatMessageJob $job) use ($room, $expected): bool {
            return $job->roomId === $room->id
                && $job->delay !== null
                && $expected->equalTo(Carbon::instance($job->delay));
        });
    }

    public function test_schedule_accepts_max_delay_minutes_boundary(): void
    {
        Queue::fake();
        $now = Carbon::parse('2026-03-29 10:00:00', 'UTC');
        Carbon::setTestNow($now);

        $room = Room::create(['name' => 'Test']);

        $response = $this->postJson("/api/rooms/{$room->id}/schedule", [
            'user_id' => 1,
            'content' => 'Day later',
            'delay_minutes' => 1440,
        ]);

        $response->assertStatus(202)->assertJsonPath('delay_minutes', 1440);

        $expected = $now->copy()->addMinutes(1440);
        Queue::assertPushed(SendChatMessageJob::class, fn (SendChatMessageJob $job): bool => $expected->equalTo(Carbon::instance($job->delay)));
    }

    public function test_schedule_does_not_persist_message_immediately(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-03-29 10:00:00', 'UTC'));

        $room = Room::create(['name' => 'Test']);

        $this->postJson("/api/rooms/{$room->id}/schedule", [
            'user_id' => 1,
            'content' => 'Not yet',
            'delay_minutes' => 30,
        ])->assertStatus(202);

        $this->assertDatabaseMissing('messages', [
            'room_id' => $room->id,
            'content' => 'Not yet',
        ]);
    }

    public function test_schedule_requires_user_id_and_content(): void
    {
        Queue::fake();
        $room = Room::create(['name' => 'Test']);

        $response = $this->postJson("/api/rooms/{$room->id}/schedule", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'content']);
        Queue::assertNothingPushed();
    }

    public function test_schedule_rejects_invalid_user_id(): void
    {
        Queue::fake();
        $room = Room::create(['name' => 'Test']);

        $response = $this->postJson("/api/rooms/{$room->id}/schedule", [
            'user_id' => 0,
            'content' => 'Later',
            'delay_minutes' => 5,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['user_id']);
        Queue::assertNothingPushed();
    }

    public function test_schedule_validates_delay_minutes_above_max(): void
    {
        Queue::fake();
        $room = Room::create(['name' => 'Test']);

        $response = $this->postJson("/api/rooms/{$room->id}/schedule", [
            'user_id' => 1,
            'content' => 'Later',
            'delay_minutes' => 2000,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['delay_minutes']);
        Queue::assertNothingPushed();
    }

    public function test_schedule_validates_delay_minutes_below_min(): void
    {
        Queue::fake();
        $room = Room::create(['name' => 'Test']);

        $response = $this->postJson("/api/rooms/{$room->id}/schedule", [
            'user_id' => 1,
            'content' => 'Later',
            'delay_minutes' => 0,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['delay_minutes']);
        Queue::assertNothingPushed();
    }

    public function test_schedule_validates_delay_minutes_integer(): void
    {
        Queue::fake();
        $room = Room::create(['name' => 'Test']);

        $response = $this->postJson("/api/rooms/{$room->id}/schedule", [
            'user_id' => 1,
            'content' => 'Later',
            'delay_minutes' => 'not-an-int',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['delay_minutes']);
        Queue::assertNothingPushed();
    }

    public function test_schedule_returns_404_for_missing_room(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/rooms/99999/schedule', [
            'user_id' => 1,
            'content' => 'Later',
            'delay_minutes' => 5,
        ]);

        $response->assertStatus(404);
        Queue::assertNothingPushed();
    }
}
