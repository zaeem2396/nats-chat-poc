<?php

namespace Tests\Feature;

use App\Models\FailedMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DlqApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension required.');
        }
        parent::setUp();
    }

    public function test_can_list_dlq_empty(): void
    {
        $response = $this->getJson('/api/dlq');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertSame([], $data['data']);
    }

    public function test_can_list_dlq_with_messages(): void
    {
        FailedMessage::create([
            'subject' => 'chat.dlq',
            'payload' => ['room_id' => 1, 'content' => 'test', 'failure_message' => 'Something failed'],
            'error_reason' => 'Something failed',
            'original_queue' => 'default',
            'original_connection' => 'nats',
            'failed_at' => now(),
        ]);

        $response = $this->getJson('/api/dlq');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.subject', 'chat.dlq')
            ->assertJsonPath('data.0.error_reason', 'Something failed')
            ->assertJsonPath('data.0.payload.room_id', 1);
    }
}
