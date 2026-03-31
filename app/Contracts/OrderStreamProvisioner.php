<?php

namespace App\Contracts;

/**
 * JetStream ORDERS stream + durable consumer setup for the order pipeline.
 */
interface OrderStreamProvisioner
{
    public function ensureStreamExists(): void;

    public function ensure(string $logicalKey): void;
}
