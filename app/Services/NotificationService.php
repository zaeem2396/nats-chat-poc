<?php

namespace App\Services;

use App\Logging\PipelineLog;
use App\Models\OrderNotification;
use Basis\Nats\Message\Msg;
use Illuminate\Support\Facades\Cache;

final class NotificationService
{
    /**
     * @param  array{id: string, type: string, data: array<string, mixed>}  $envelope
     */
    public function handlePaymentEvent(Msg $msg, array $envelope): void
    {
        $type = $envelope['type'];
        $eventId = $envelope['id'];
        $data = $envelope['data'];
        $orderId = isset($data['order_id']) ? (int) $data['order_id'] : null;

        $idem = 'order_notify:'.$type.':'.$eventId;
        if (Cache::has($idem)) {
            PipelineLog::info('NotificationService', 'Duplicate — ACK (idempotency)', [
                'message_id' => $eventId,
                'type' => $type,
            ]);
            $msg->ack();

            return;
        }

        PipelineLog::info('NotificationService', 'Processing started', [
            'message_id' => $eventId,
            'type' => $type,
            'order_id' => $orderId,
        ]);

        OrderNotification::query()->create([
            'event_type' => $type,
            'order_id' => $orderId,
            'payload' => $data,
        ]);

        Cache::put($idem, true, (int) config('nats_orders.idempotency_ttl_seconds', 86400));

        PipelineLog::info('NotificationService', 'Successfully processed', [
            'message_id' => $eventId,
            'type' => $type,
            'order_id' => $orderId,
        ]);

        $msg->ack();
    }
}
