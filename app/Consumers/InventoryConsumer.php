<?php

namespace App\Consumers;

use App\Services\InventoryService;
use App\Support\OrderPipelineEventParser;
use Basis\Nats\Message\Msg;
use Illuminate\Support\Facades\Log;

/**
 * Durable consumer on payments.completed — stock deduction + inventory.updated.
 */
final class InventoryConsumer
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    public function handle(Msg $msg): void
    {
        $parsed = OrderPipelineEventParser::parse($msg);
        if ($parsed === null) {
            Log::error('order_pipeline.inventory_worker.invalid_envelope');
            $msg->term('invalid_envelope');

            return;
        }

        $this->inventoryService->handlePaymentCompleted($msg, $parsed);
    }
}
