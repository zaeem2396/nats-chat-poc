<?php

namespace App\Console\Commands;

use App\Consumers\NotificationConsumer;
use App\Services\Nats\JetStreamOrderWorkerLoop;
use Illuminate\Console\Command;

class NatsNotificationsWorkerCommand extends Command
{
    protected $signature = 'nats:notifications-worker';

    protected $description = 'JetStream worker: payments.completed + payments.failed → notifications table + logs';

    public function handle(JetStreamOrderWorkerLoop $loop, NotificationConsumer $consumer): int
    {
        $this->info('Starting notifications worker (Ctrl+C to stop)…');
        $loop->run('notifications', [$consumer, 'handle']);

        return self::SUCCESS;
    }
}
