<?php

namespace App\Consumers;

use App\Logging\PipelineLog;
use App\Services\NotificationService;
use App\Support\JetStreamAckMeta;
use App\Support\OrderPipelineEventParser;
use Basis\Nats\Message\Msg;

/**
 * Durable consumer on payments.completed + payments.failed — persisted notifications + logs.
 */
final class NotificationConsumer
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(Msg $msg): void
    {
        $parsed = OrderPipelineEventParser::parse($msg);
        if ($parsed === null) {
            PipelineLog::error('NotificationsWorker', 'Invalid envelope — TERM', ['subject' => $msg->subject]);
            $msg->term('invalid_envelope');

            return;
        }

        PipelineLog::info('NotificationsWorker', 'Message received', [
            'message_id' => $parsed['id'],
            'type' => $parsed['type'],
            'subject' => $msg->subject,
            'jetstream_attempt' => JetStreamAckMeta::deliveryAttempt($msg),
        ]);

        $this->notificationService->handlePaymentEvent($msg, $parsed);
    }
}
