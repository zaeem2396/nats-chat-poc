<?php

namespace Tests\Feature;

use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension required.');
        }
        parent::setUp();
    }

    public function test_can_get_metrics(): void
    {
        $response = $this->getJson('/api/metrics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'messages_processed',
                'messages_failed',
                'retries_count',
                'avg_processing_time_ms',
            ]);
    }
}
