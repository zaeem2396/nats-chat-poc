<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NatsV2FeaturesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension required.');
        }
        parent::setUp();
    }

    public function test_smoke_returns_package_version_and_flags(): void
    {
        $response = $this->getJson('/api/nats/v2/smoke');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'laravel_nats',
                'nats_v2_ping',
                'multi_header_publish_ok',
            ]);
        $this->assertIsBool($response->json('nats_v2_ping'));
        $this->assertIsBool($response->json('multi_header_publish_ok'));
    }
}
