<?php

namespace App\Consumers;

use App\Logging\PipelineLog;
use App\Models\Order;
use App\Support\JetStreamAckMeta;
use App\Support\OrderPipelineEventParser;
use Basis\Nats\Message\Msg;
use Illuminate\Support\Facades\Cache;

/**
 * Durable consumer on orders.created — ingress validation / pipeline stage before payment.
 */
final class OrderConsumer
{
    public function handle(Msg $msg): void
    {
        $parsed = OrderPipelineEventParser::parse($msg);
        if ($parsed === null) {
            PipelineLog::error('OrdersWorker', 'Invalid envelope — TERM', ['subject' => $msg->subject]);
            $msg->term('invalid_envelope');

            return;
        }

        $eventId = $parsed['id'];
        $attempt = JetStreamAckMeta::deliveryAttempt($msg);

        PipelineLog::info('OrdersWorker', 'Message received', [
            'message_id' => $eventId,
            'subject' => $msg->subject,
            'jetstream_attempt' => $attempt,
        ]);

        $idemKey = 'order_ingress:'.$eventId;
        if (Cache::has($idemKey)) {
            PipelineLog::info('OrdersWorker', 'Duplicate — already processed (idempotency) — ACK', [
                'message_id' => $eventId,
            ]);
            $msg->ack();

            return;
        }

        $orderId = (int) ($parsed['data']['order_id'] ?? 0);
        if ($orderId < 1) {
            PipelineLog::error('OrdersWorker', 'missing_order_id — TERM', ['message_id' => $eventId]);
            $msg->term('missing_order_id');

            return;
        }

        PipelineLog::info('OrdersWorker', 'Processing started', [
            'message_id' => $eventId,
            'order_id' => $orderId,
        ]);

        Order::query()->whereKey($orderId)->update([
            'pipeline_stage' => 'awaiting_payment',
        ]);

        Cache::put($idemKey, true, (int) config('nats_orders.idempotency_ttl_seconds', 86400));

        PipelineLog::info('OrdersWorker', 'Successfully processed', [
            'message_id' => $eventId,
            'order_id' => $orderId,
        ]);

        $msg->ack();
    }
}
