<?php

namespace App\Console\Commands;

use App\Consumers\OrderConsumer;
use App\Services\Nats\JetStreamOrderWorkerLoop;
use Illuminate\Console\Command;

class NatsOrdersWorkerCommand extends Command
{
    protected $signature = 'nats:orders-worker';

    protected $description = 'JetStream worker: durable consumer on orders.created (ingress / pipeline stage)';

    public function handle(JetStreamOrderWorkerLoop $loop, OrderConsumer $consumer): int
    {
        $this->info('Starting orders worker (Ctrl+C to stop)…');
        $loop->run('orders', [$consumer, 'handle']);

        return self::SUCCESS;
    }
}
