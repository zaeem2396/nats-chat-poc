<?php

namespace App\Console\Commands;

use App\Consumers\InventoryConsumer;
use App\Services\Nats\JetStreamOrderWorkerLoop;
use Illuminate\Console\Command;

class NatsInventoryWorkerCommand extends Command
{
    protected $signature = 'nats:inventory-worker';

    protected $description = 'JetStream worker: payments.completed → deduct stock → inventory.updated';

    public function handle(JetStreamOrderWorkerLoop $loop, InventoryConsumer $consumer): int
    {
        $this->info('Starting inventory worker (Ctrl+C to stop)…');
        $loop->run('inventory', [$consumer, 'handle']);

        return self::SUCCESS;
    }
}
