<?php

namespace App\Services;

use App\Contracts\OrderStreamProvisioner;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LaravelNats\Laravel\Facades\NatsV2;

final class OrderService
{
    public function __construct(
        private readonly OrderStreamProvisioner $orderStreamProvisioner,
    ) {}

    public function placeOrder(int $userId, string $sku, int $quantity, int $totalCents): Order
    {
        $this->orderStreamProvisioner->ensureStreamExists();

        return DB::transaction(function () use ($userId, $sku, $quantity, $totalCents): Order {
            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'sku' => $sku,
                'quantity' => $quantity,
                'total_cents' => $totalCents,
                'status' => 'pending',
                'pipeline_stage' => 'created',
            ]);

            $stream = (string) config('nats_orders.stream_name', 'ORDERS');
            $subject = (string) config('nats_orders.subjects.orders_created', 'orders.created');

            NatsV2::jetStreamPublish(
                $stream,
                $subject,
                [
                    'idempotency_key' => $order->uuid,
                    'order_id' => $order->id,
                    'order_uuid' => $order->uuid,
                    'user_id' => $userId,
                    'sku' => $sku,
                    'quantity' => $quantity,
                    'total_cents' => $totalCents,
                ],
                useEnvelope: true,
                waitForAck: true,
            );

            Log::info('order_pipeline.order.created_published', [
                'order_id' => $order->id,
                'subject' => $subject,
            ]);

            return $order->fresh();
        });
    }
}
