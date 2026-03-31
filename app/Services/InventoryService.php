<?php

namespace App\Services;

use App\Models\InventoryItem;
use Basis\Nats\Message\Msg;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        Log::info('order_pipeline.inventory.received', [
            'order_id' => $orderId,
            'sku' => $sku,
            'quantity' => $qty,
            'event_id' => $eventId,
        ]);

        if ($sku === '' || $qty < 1) {
            $this->dlq->moveToDlq(
                $msg,
                (string) config('nats_orders.subjects.inventory_dlq', 'inventory.dlq'),
                $consumer,
                'Invalid sku or quantity',
                1,
            );

            return;
        }

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
            Log::warning('order_pipeline.inventory.insufficient_stock_nack', ['sku' => $sku, 'qty' => $qty]);
            $msg->nack(2.0);

            return;
        }

        Log::info('order_pipeline.inventory.processed', ['order_id' => $orderId, 'sku' => $sku]);
        $msg->ack();
    }
}
