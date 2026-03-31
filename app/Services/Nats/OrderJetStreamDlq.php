<?php

namespace App\Services\Nats;

use App\Logging\PipelineLog;
use App\Models\FailedMessage;
use Basis\Nats\Message\Msg;
use Illuminate\Support\Facades\Log;
use LaravelNats\Laravel\Facades\NatsV2;

/**
 * After JetStream max_deliver is exhausted (or poison message): TERM + publish audit to *.dlq + DB row for replay.
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

        $sourceSubject = $msg->subject !== '' ? $msg->subject : null;

        FailedMessage::create([
            'subject' => $dlqSubject,
            'source_subject' => $sourceSubject,
            'payload' => $payload,
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
                    'source_subject' => $sourceSubject,
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

        PipelineLog::error('Dlq', 'Moved to DLQ after max retries or terminal poison', [
            'dlq_subject' => $dlqSubject,
            'source_subject' => $sourceSubject,
            'consumer' => $consumerName,
            'reason' => $reason,
            'attempts' => $attempts,
            'message_id' => $payload['id'] ?? null,
        ]);

        if ($msg->replyTo !== null && $msg->replyTo !== '') {
            $msg->term($reason);
        }
    }
}
