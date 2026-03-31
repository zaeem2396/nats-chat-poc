<?php

namespace App\Services;

use App\Models\OrderNotification;
use Basis\Nats\Message\Msg;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
            Log::info('order_pipeline.notify.duplicate_skip', ['type' => $type, 'event_id' => $eventId]);
            $msg->ack();

            return;
        }

        OrderNotification::query()->create([
            'event_type' => $type,
            'order_id' => $orderId,
            'payload' => $data,
        ]);

        Log::info('order_pipeline.notify.stored', [
            'type' => $type,
            'order_id' => $orderId,
            'event_id' => $eventId,
        ]);

        Cache::put($idem, true, (int) config('nats_orders.idempotency_ttl_seconds', 86400));

        $msg->ack();
    }
}
