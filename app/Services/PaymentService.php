<?php

namespace App\Services;

use App\Exceptions\TransientPipelineException;
use App\Logging\PipelineLog;
use App\Models\Order;
use App\Models\Payment;
use App\Support\JetStreamAckMeta;
use Basis\Nats\Message\Msg;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LaravelNats\Laravel\Facades\NatsV2;

final class PaymentService
{
    public function __construct(
        private readonly \App\Services\Nats\OrderJetStreamDlq $dlq,
    ) {}

    /**
     * @param  array{id: string, type: string, version: string, data: array<string, mixed>}  $envelope
     *
     * @throws TransientPipelineException JetStream will redeliver after NAK (until max_deliver).
     */
    public function handleOrderCreated(Msg $msg, array $envelope): void
    {
        $data = $envelope['data'];
        $orderId = (int) ($data['order_id'] ?? 0);
        $eventId = $envelope['id'];
        $stream = (string) config('nats_orders.stream_name', 'ORDERS');
        $consumer = (string) config('nats_orders.consumers.payments.durable_name', 'svc_payments_order_created');
        $maxDeliver = JetStreamAckMeta::maxDeliverForConsumer('payments');
        $attempt = JetStreamAckMeta::deliveryAttempt($msg);
        $ttl = (int) config('nats_orders.idempotency_ttl_seconds', 86400);

        PipelineLog::info('PaymentService', 'Message received', [
            'message_id' => $eventId,
            'subject' => $msg->subject,
            'order_id' => $orderId,
            'jetstream_attempt' => $attempt,
            'jetstream_max_deliver' => $maxDeliver,
        ]);

        if ($orderId < 1) {
            PipelineLog::error('PaymentService', 'Invalid order_id — sending to DLQ', [
                'message_id' => $eventId,
                'subject' => $msg->subject,
            ]);
            $this->dlq->moveToDlq(
                $msg,
                (string) config('nats_orders.subjects.payments_dlq', 'payments.dlq'),
                $consumer,
                'Invalid order_id in envelope',
                $attempt,
            );

            return;
        }

        $processedKey = 'payment_evt_processed:'.$eventId;
        if (Cache::has($processedKey)) {
            PipelineLog::info('PaymentService', 'Already processed (idempotency) — ACK', [
                'message_id' => $eventId,
                'order_id' => $orderId,
            ]);
            $msg->ack();

            return;
        }

        if (Payment::query()->where('order_id', $orderId)->where('status', 'completed')->exists()) {
            PipelineLog::info('PaymentService', 'Duplicate delivery; completed payment exists — ACK', [
                'message_id' => $eventId,
                'order_id' => $orderId,
            ]);
            Cache::put($processedKey, true, $ttl);
            $msg->ack();

            return;
        }

        $transientPct = (int) config('nats_orders.payment_transient_fail_percent', 35);
        $terminalPct = (int) config('nats_orders.payment_terminal_fail_percent', 10);

        PipelineLog::info('PaymentService', 'Processing started', [
            'message_id' => $eventId,
            'order_id' => $orderId,
            'jetstream_attempt' => $attempt,
        ]);

        $roll = random_int(1, 100);

        if ($roll <= $transientPct) {
            if ($attempt >= $maxDeliver) {
                PipelineLog::warning('PaymentService', 'Simulated transient failure but max JetStream deliveries reached → DLQ', [
                    'message_id' => $eventId,
                    'order_id' => $orderId,
                    'jetstream_attempt' => $attempt,
                    'max_deliver' => $maxDeliver,
                ]);
                $this->dlq->moveToDlq(
                    $msg,
                    (string) config('nats_orders.subjects.payments_dlq', 'payments.dlq'),
                    $consumer,
                    'Simulated transient failure after max_deliver JetStream deliveries',
                    $attempt,
                );

                return;
            }

            PipelineLog::warning('PaymentService', "Payment failed → retry {$attempt}/{$maxDeliver} (JetStream NAK)", [
                'message_id' => $eventId,
                'subject' => $msg->subject,
                'order_id' => $orderId,
                'jetstream_attempt' => $attempt,
            ]);
            throw new TransientPipelineException('simulated_transient_payment_failure');
        }

        $amountCents = (int) ($data['total_cents'] ?? 0);

        if ($roll <= $transientPct + $terminalPct) {
            PipelineLog::warning('PaymentService', 'Terminal decline (simulated) — publishing payments.failed', [
                'message_id' => $eventId,
                'order_id' => $orderId,
            ]);
            DB::transaction(function () use ($orderId, $amountCents, $data, $stream): void {
                Payment::query()->create([
                    'order_id' => $orderId,
                    'status' => 'failed',
                    'transaction_ref' => null,
                    'amount_cents' => $amountCents,
                ]);
                Order::query()->whereKey($orderId)->update(['status' => 'payment_failed', 'pipeline_stage' => 'payment_failed']);

                NatsV2::jetStreamPublish(
                    $stream,
                    (string) config('nats_orders.subjects.payments_failed', 'payments.failed'),
                    [
                        'idempotency_key' => 'pay-fail-'.$orderId.'-'.($data['order_uuid'] ?? ''),
                        'order_id' => $orderId,
                        'order_uuid' => $data['order_uuid'] ?? null,
                        'reason' => 'simulated_decline',
                        'amount_cents' => $amountCents,
                    ],
                    useEnvelope: true,
                    waitForAck: true,
                );
            });

            Cache::put($processedKey, true, $ttl);
            PipelineLog::info('PaymentService', 'Successfully processed (declined path)', [
                'message_id' => $eventId,
                'order_id' => $orderId,
            ]);
            $msg->ack();

            return;
        }

        DB::transaction(function () use ($orderId, $amountCents, $data, $stream): void {
            $ref = 'txn_'.bin2hex(random_bytes(8));
            Payment::query()->create([
                'order_id' => $orderId,
                'status' => 'completed',
                'transaction_ref' => $ref,
                'amount_cents' => $amountCents,
            ]);
            Order::query()->whereKey($orderId)->update(['status' => 'paid', 'pipeline_stage' => 'payment_completed']);

            NatsV2::jetStreamPublish(
                $stream,
                (string) config('nats_orders.subjects.payments_completed', 'payments.completed'),
                [
                    'idempotency_key' => 'pay-ok-'.$orderId,
                    'order_id' => $orderId,
                    'order_uuid' => $data['order_uuid'] ?? null,
                    'transaction_ref' => $ref,
                    'amount_cents' => $amountCents,
                    'sku' => $data['sku'] ?? null,
                    'quantity' => (int) ($data['quantity'] ?? 0),
                ],
                useEnvelope: true,
                waitForAck: true,
            );
        });

        Cache::put($processedKey, true, $ttl);
        PipelineLog::info('PaymentService', 'Successfully processed (payment completed)', [
            'message_id' => $eventId,
            'order_id' => $orderId,
        ]);
        $msg->ack();
    }
}
