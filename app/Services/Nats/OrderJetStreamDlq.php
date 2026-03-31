<?php

namespace App\Services\Nats;

use App\Models\FailedMessage;
use Basis\Nats\Message\Msg;
use Illuminate\Support\Facades\Log;
use LaravelNats\Laravel\Facades\NatsV2;

/**
 * Terminal handling: publish to *.dlq stream subject + persist audit row.
 */
final class OrderJetStreamDlq
{
    public function moveToDlq(
        Msg $msg,
        string $dlqSubject,
        string $consumerName,
        string $reason,
        int $attempts = 1,
    ): void {
        $raw = (string) $msg->payload->body;
        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            $payload = ['raw' => $raw];
        }

        FailedMessage::create([
            'subject' => $dlqSubject,
            'payload' => array_merge($payload, [
                'dlq_reason' => $reason,
                'source_consumer' => $consumerName,
            ]),
            'error_message' => $reason,
            'error_reason' => $reason,
            'attempts' => $attempts,
            'original_queue' => $consumerName,
            'original_connection' => 'jetstream',
            'failed_at' => now(),
        ]);

        $stream = (string) config('nats_orders.stream_name', 'ORDERS');
        try {
            NatsV2::jetStreamPublish(
                $stream,
                $dlqSubject,
                [
                    'source_consumer' => $consumerName,
                    'failure_message' => $reason,
                    'attempts' => $attempts,
                    'original_envelope' => $payload,
                ],
                useEnvelope: true,
                waitForAck: true,
            );
        } catch (\Throwable $e) {
            Log::critical('order_pipeline.dlq_publish_failed', [
                'error' => $e->getMessage(),
                'subject' => $dlqSubject,
            ]);
        }

        Log::error('order_pipeline.dlq', [
            'subject' => $dlqSubject,
            'consumer' => $consumerName,
            'reason' => $reason,
            'attempts' => $attempts,
        ]);

        if ($msg->replyTo !== null && $msg->replyTo !== '') {
            $msg->term($reason);
        }
    }
}
