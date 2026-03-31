<?php

namespace App\Consumers;

use App\Models\Order;
use App\Support\OrderPipelineEventParser;
use Basis\Nats\Message\Msg;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Durable consumer on orders.created — ingress validation / pipeline stage before payment.
 */
final class OrderConsumer
{
    public function handle(Msg $msg): void
    {
        $parsed = OrderPipelineEventParser::parse($msg);
        if ($parsed === null) {
            Log::error('order_pipeline.orders_worker.invalid_envelope');
            $msg->term('invalid_envelope');

            return;
        }

        $eventId = $parsed['id'];
        $idemKey = 'order_ingress:'.$eventId;
        if (Cache::has($idemKey)) {
            Log::info('order_pipeline.orders_worker.duplicate_ack', ['event_id' => $eventId]);
            $msg->ack();

            return;
        }

        $orderId = (int) ($parsed['data']['order_id'] ?? 0);
        if ($orderId < 1) {
            $msg->term('missing_order_id');

            return;
        }

        Order::query()->whereKey($orderId)->update([
            'pipeline_stage' => 'awaiting_payment',
        ]);

        Cache::put($idemKey, true, (int) config('nats_orders.idempotency_ttl_seconds', 86400));

        Log::info('order_pipeline.orders_worker.processed', [
            'event_id' => $eventId,
            'order_id' => $orderId,
        ]);

        $msg->ack();
    }
}
