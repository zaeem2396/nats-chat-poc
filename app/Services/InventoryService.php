<?php

namespace App\Services;

use App\Logging\PipelineLog;
use App\Models\InventoryItem;
use App\Support\JetStreamAckMeta;
use Basis\Nats\Message\Msg;
use Illuminate\Support\Facades\DB;
use LaravelNats\Laravel\Facades\NatsV2;

final class InventoryService
{
    public function __construct(
        private readonly \App\Services\Nats\OrderJetStreamDlq $dlq,
    ) {}

    /**
     * @param  array{id: string, data: array<string, mixed>}  $envelope
     */
    public function handlePaymentCompleted(Msg $msg, array $envelope): void
    {
        $data = $envelope['data'];
        $orderId = (int) ($data['order_id'] ?? 0);
        $sku = (string) ($data['sku'] ?? '');
        $qty = (int) ($data['quantity'] ?? 0);
        $eventId = $envelope['id'];
        $stream = (string) config('nats_orders.stream_name', 'ORDERS');
        $consumer = (string) config('nats_orders.consumers.inventory.durable_name', 'svc_inventory_payments_completed');
        $attempt = JetStreamAckMeta::deliveryAttempt($msg);
        $maxDeliver = JetStreamAckMeta::maxDeliverForConsumer('inventory');

        PipelineLog::info('InventoryService', 'Message received', [
            'message_id' => $eventId,
            'subject' => $msg->subject,
            'order_id' => $orderId,
            'jetstream_attempt' => $attempt,
            'jetstream_max_deliver' => $maxDeliver,
        ]);

        if ($sku === '' || $qty < 1) {
            PipelineLog::error('InventoryService', 'Invalid sku/qty — DLQ', ['message_id' => $eventId]);
            $this->dlq->moveToDlq(
                $msg,
                (string) config('nats_orders.subjects.inventory_dlq', 'inventory.dlq'),
                $consumer,
                'Invalid sku or quantity',
                $attempt,
            );

            return;
        }

        PipelineLog::info('InventoryService', 'Processing started', [
            'message_id' => $eventId,
            'sku' => $sku,
            'quantity' => $qty,
        ]);

        $deducted = DB::transaction(function () use ($sku, $qty, $orderId, $stream): bool {
            /** @var InventoryItem|null $row */
            $row = InventoryItem::query()->where('sku', $sku)->lockForUpdate()->first();
            if ($row === null || $row->quantity < $qty) {
                return false;
            }

            $row->decrement('quantity', $qty);

            NatsV2::jetStreamPublish(
                $stream,
                (string) config('nats_orders.subjects.inventory_updated', 'inventory.updated'),
                [
                    'idempotency_key' => 'inv-'.$orderId.'-'.$sku,
                    'order_id' => $orderId,
                    'sku' => $sku,
                    'quantity_deducted' => $qty,
                    'remaining' => $row->fresh()->quantity,
                ],
                useEnvelope: true,
                waitForAck: true,
            );

            return true;
        });

        if (! $deducted) {
            if ($attempt >= $maxDeliver) {
                PipelineLog::warning('InventoryService', 'Insufficient stock after max JetStream deliveries → DLQ', [
                    'message_id' => $eventId,
                    'sku' => $sku,
                    'jetstream_attempt' => $attempt,
                ]);
                $this->dlq->moveToDlq(
                    $msg,
                    (string) config('nats_orders.subjects.inventory_dlq', 'inventory.dlq'),
                    $consumer,
                    'Insufficient stock after max_deliver',
                    $attempt,
                );

                return;
            }

            $delay = (float) config('nats_orders.nack_delay_seconds', 2.0);
            PipelineLog::warning('InventoryService', "Insufficient stock → retry {$attempt}/{$maxDeliver} (NAK)", [
                'message_id' => $eventId,
                'sku' => $sku,
                'qty' => $qty,
            ]);
            $msg->nack($delay);

            return;
        }

        PipelineLog::info('InventoryService', 'Successfully processed', [
            'message_id' => $eventId,
            'order_id' => $orderId,
            'sku' => $sku,
        ]);
        $msg->ack();
    }
}
