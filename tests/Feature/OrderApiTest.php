<?php

namespace Tests\Feature;

use App\Contracts\OrderStreamProvisioner;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelNats\Laravel\NatsV2Gateway;
use Mockery;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension required.');
        }
        parent::setUp();
    }

    public function test_post_orders_creates_order_and_publishes_jetstream(): void
    {
        $provisioner = Mockery::mock(OrderStreamProvisioner::class);
        $provisioner->shouldReceive('ensureStreamExists')->once();
        $this->app->instance(OrderStreamProvisioner::class, $provisioner);

        $gateway = $this->app->make(NatsV2Gateway::class);
        $mock = Mockery::mock($gateway);
        $mock->shouldReceive('jetStreamPublish')->once()->withAnyArgs();
        $this->app->instance('nats.v2', $mock);

        $response = $this->postJson('/api/orders', [
            'user_id' => 1,
            'sku' => 'SKU-DEMO',
            'quantity' => 2,
            'total_cents' => 1999,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user_id', 1)
            ->assertJsonPath('sku', 'SKU-DEMO')
            ->assertJsonPath('quantity', 2)
            ->assertJsonPath('total_cents', 1999);

        $this->assertDatabaseHas('orders', [
            'user_id' => 1,
            'sku' => 'SKU-DEMO',
            'quantity' => 2,
        ]);
    }

    public function test_get_orders_index(): void
    {
        Order::query()->create([
            'uuid' => '00000000-0000-0000-0000-000000000099',
            'user_id' => 1,
            'sku' => 'SKU-DEMO',
            'quantity' => 1,
            'total_cents' => 100,
            'status' => 'pending',
            'pipeline_stage' => null,
        ]);

        $response = $this->getJson('/api/orders');

        $response->assertStatus(200)->assertJsonCount(1);
    }

    public function test_orders_validation(): void
    {
        $gateway = $this->app->make(NatsV2Gateway::class);
        $mock = Mockery::mock($gateway);
        $mock->shouldNotReceive('jetStreamPublish');
        $this->app->instance('nats.v2', $mock);

        $response = $this->postJson('/api/orders', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['user_id', 'sku', 'quantity', 'total_cents']);
    }
}
