<?php

namespace App\Console\Commands;

use App\Consumers\PaymentConsumer;
use App\Services\Nats\JetStreamOrderWorkerLoop;
use Illuminate\Console\Command;

class NatsPaymentsWorkerCommand extends Command
{
    protected $signature = 'nats:payments-worker';

    protected $description = 'JetStream worker: payment simulation → payments.completed | payments.failed (NACK retries, DLQ)';

    public function handle(JetStreamOrderWorkerLoop $loop, PaymentConsumer $consumer): int
    {
        $this->info('Starting payments worker (Ctrl+C to stop)…');
        $loop->run('payments', [$consumer, 'handle']);

        return self::SUCCESS;
    }
}
