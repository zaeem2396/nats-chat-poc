<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Basis\Nats\Message\Msg;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LaravelNats\Laravel\Facades\NatsV2;

final class PaymentService
{
    public function __construct(
        private readonly \App\Services\Nats\OrderJetStreamDlq $dlq,
    ) {}

    /**
     * @param  array{id: string, type: string, version: string, data: array<string, mixed>}  $envelope
     */
    public function handleOrderCreated(Msg $msg, array $envelope): void
    {
        $data = $envelope['data'];
        $orderId = (int) ($data['order_id'] ?? 0);
        $eventId = $envelope['id'];
        $stream = (string) config('nats_orders.stream_name', 'ORDERS');
        $consumer = (string) config('nats_orders.consumers.payments.durable_name', 'svc_payments_order_created');

        if ($orderId < 1) {
            $this->dlq->moveToDlq(
                $msg,
                (string) config('nats_orders.subjects.payments_dlq', 'payments.dlq'),
                $consumer,
                'Invalid order_id in envelope',
                1,
            );

            return;
        }

        if (Payment::query()->where('order_id', $orderId)->where('status', 'completed')->exists()) {
            Log::info('order_pipeline.payment.duplicate_skip', ['order_id' => $orderId, 'event_id' => $eventId]);
            $msg->ack();

            return;
        }

        $attemptKey = 'order_pay_attempt:'.$eventId;
        $n = Cache::increment($attemptKey);
        if ($n === false) {
            Cache::put($attemptKey, 1, 7200);
            $n = 1;
        }

        $maxAttempts = (int) config('nats_orders.max_processing_attempts_before_dlq', 5);
        $transientPct = (int) config('nats_orders.payment_transient_fail_percent', 35);
        $terminalPct = (int) config('nats_orders.payment_terminal_fail_percent', 10);

        Log::info('order_pipeline.payment.received', [
            'order_id' => $orderId,
            'event_id' => $eventId,
            'delivery_attempt' => $n,
        ]);

        if ($n > $maxAttempts) {
            $this->dlq->moveToDlq(
                $msg,
                (string) config('nats_orders.subjects.payments_dlq', 'payments.dlq'),
                $consumer,
                'Exceeded max processing attempts (payment worker)',
                $n,
            );
            Cache::forget($attemptKey);

            return;
        }

        $roll = random_int(1, 100);

        if ($roll <= $transientPct && $n < $maxAttempts) {
            Log::warning('order_pipeline.payment.transient_nack', [
                'order_id' => $orderId,
                'event_id' => $eventId,
                'attempt' => $n,
            ]);
            $msg->nack(1.5);

            return;
        }

        $amountCents = (int) ($data['total_cents'] ?? 0);

        if ($roll <= $transientPct + $terminalPct || $n >= $maxAttempts) {
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
                        'idempotency_key' => 'pay-fail-'.$orderId.'-'.$data['order_uuid'],
                        'order_id' => $orderId,
                        'order_uuid' => $data['order_uuid'] ?? null,
                        'reason' => 'simulated_decline_or_max_attempts',
                        'amount_cents' => $amountCents,
                    ],
                    useEnvelope: true,
                    waitForAck: true,
                );
            });

            Log::info('order_pipeline.payment.failed_published', ['order_id' => $orderId, 'event_id' => $eventId]);
            $msg->ack();
            Cache::forget($attemptKey);

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

        Log::info('order_pipeline.payment.completed_published', ['order_id' => $orderId, 'event_id' => $eventId]);
        $msg->ack();
        Cache::forget($attemptKey);
    }
}
