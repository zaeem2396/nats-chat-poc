<?php

namespace App\Console\Commands;

use App\Services\JetStream\DlqStreamBootstrap;
use Illuminate\Console\Command;

/**
 * One-off: ensure DLQ stream and consumer exist so failed jobs published to chat.dlq are captured.
 */
class DlqBootstrapCommand extends Command
{
    protected $signature = 'nats-chat:dlq-bootstrap';

    protected $description = 'Create JetStream stream and consumer for chat.dlq (run once or before workers)';

    public function handle(): int
    {
        $this->info('Bootstrapping DLQ stream and consumer...');
        (new DlqStreamBootstrap)->ensureStreamAndConsumer();
        $this->info('Done. DLQ subject chat.dlq is now persisted by JetStream.');

        return self::SUCCESS;
    }
}
