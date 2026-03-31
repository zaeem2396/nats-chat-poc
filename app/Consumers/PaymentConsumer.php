<?php

namespace App\Consumers;

use App\Exceptions\TransientPipelineException;
use App\Logging\PipelineLog;
use App\Services\PaymentService;
use App\Support\JetStreamAckMeta;
use App\Support\OrderPipelineEventParser;
use Basis\Nats\Message\Msg;
use Illuminate\Support\Facades\Log;

/**
 * Durable consumer on orders.created — payment simulation, payments.completed | payments.failed.
 *
 * JetStream: explicit ack, NAK on transient failure (redelivery until max_deliver), term via DLQ helper when exhausted.
 */
final class PaymentConsumer
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function handle(Msg $msg): void
    {
        $parsed = OrderPipelineEventParser::parse($msg);
        if ($parsed === null) {
            PipelineLog::error('PaymentConsumer', 'Invalid envelope — TERM', [
                'subject' => $msg->subject,
            ]);
            $msg->term('invalid_envelope');

            return;
        }

        try {
            $this->paymentService->handleOrderCreated($msg, $parsed);
        } catch (TransientPipelineException $e) {
            $delay = (float) config('nats_orders.nack_delay_seconds', 2.0);
            $attempt = JetStreamAckMeta::deliveryAttempt($msg);
            $max = JetStreamAckMeta::maxDeliverForConsumer('payments');
            PipelineLog::warning('PaymentConsumer', 'Transient failure — NAK for JetStream redelivery', [
                'message_id' => $parsed['id'],
                'subject' => $msg->subject,
                'jetstream_attempt' => $attempt,
                'nack_delay_seconds' => $delay,
                'error' => $e->getMessage(),
            ]);
            $msg->nack($delay);
        } catch (\Throwable $e) {
            Log::error('order_pipeline.payments_worker.unhandled', [
                'message_id' => $parsed['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
