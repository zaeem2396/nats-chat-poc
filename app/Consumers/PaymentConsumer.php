<?php

namespace App\Consumers;

use App\Services\PaymentService;
use App\Support\OrderPipelineEventParser;
use Basis\Nats\Message\Msg;
use Illuminate\Support\Facades\Log;

/**
 * Durable consumer on orders.created — payment simulation, payments.completed | payments.failed.
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
            Log::error('order_pipeline.payments_worker.invalid_envelope');
            $msg->term('invalid_envelope');

            return;
        }

        $this->paymentService->handleOrderCreated($msg, $parsed);
    }
}
