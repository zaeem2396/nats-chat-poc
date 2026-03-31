<?php

namespace App\Consumers;

use App\Services\NotificationService;
use App\Support\OrderPipelineEventParser;
use Basis\Nats\Message\Msg;
use Illuminate\Support\Facades\Log;

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
            Log::error('order_pipeline.notifications_worker.invalid_envelope');
            $msg->term('invalid_envelope');

            return;
        }

        $this->notificationService->handlePaymentEvent($msg, $parsed);
    }
}
