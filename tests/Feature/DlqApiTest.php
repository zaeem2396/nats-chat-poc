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
            'subject' => 'payments.dlq',
            'source_subject' => 'orders.created',
            'payload' => [
                'id' => 'evt_test_1',
                'type' => 'orders.created',
                'version' => 'v1',
                'data' => ['order_id' => 99],
            ],
            'error_message' => 'Exceeded max_deliver',
            'error_reason' => 'Exceeded max_deliver',
            'attempts' => 5,
            'original_queue' => 'svc_payments_order_created',
            'original_connection' => 'jetstream',
            'failed_at' => now(),
        ]);

        $response = $this->getJson('/api/dlq');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.subject', 'payments.dlq')
            ->assertJsonPath('data.0.source_subject', 'orders.created')
            ->assertJsonPath('data.0.error_message', 'Exceeded max_deliver')
            ->assertJsonPath('data.0.attempts', 5)
            ->assertJsonPath('data.0.payload.id', 'evt_test_1')
            ->assertJsonPath('data.0.payload.data.order_id', 99);
    }
}
