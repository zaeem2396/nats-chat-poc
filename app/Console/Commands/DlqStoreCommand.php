<?php

namespace App\Console\Commands;

use App\Models\FailedMessage;
use App\Services\JetStream\DlqStreamBootstrap;
use Illuminate\Console\Command;
use LaravelNats\Laravel\Facades\Nats;
use Throwable;

/**
 * Long-running worker: fetches messages from DLQ stream, stores in failed_messages table, acks.
 */
class DlqStoreCommand extends Command
{
    protected $signature = 'nats-chat:dlq-store
                            {--connection=default : NATS connection name}
                            {--timeout=5 : Fetch timeout in seconds}
                            {--no-wait : Return immediately when no message}';

    protected $description = 'Consume from chat.dlq stream and store failed messages in DB';

    public function handle(): int
    {
        $connectionName = $this->option('connection');
        $timeout = (float) $this->option('timeout');
        $noWait = $this->option('no-wait');

        $this->info('Bootstrapping DLQ stream and consumer...');
        (new DlqStreamBootstrap)->ensureStreamAndConsumer();

        $js = Nats::jetstream($connectionName);
        if (! $js->isAvailable()) {
            $this->error('JetStream not available on connection: '.$connectionName);

            return self::FAILURE;
        }

        $streamName = DlqStreamBootstrap::STREAM_NAME;
        $consumerName = DlqStreamBootstrap::CONSUMER_NAME;

        $this->info("Consuming from {$streamName} / {$consumerName}. Ctrl+C to stop.");

        while (true) {
            try {
                $msg = $js->fetchNextMessage($streamName, $consumerName, $timeout, $noWait);
                if ($msg === null) {
                    continue;
                }

                $payload = json_decode($msg->getPayload(), true);
                if (! is_array($payload)) {
                    $js->ack($msg);
                    continue;
                }

                FailedMessage::create([
                    'subject' => method_exists($msg, 'getSubject') ? $msg->getSubject() : DlqStreamBootstrap::SUBJECT,
                    'payload' => $payload,
                    'error_reason' => $payload['failure_message'] ?? $payload['failure_exception'] ?? null,
                    'original_queue' => $payload['original_queue'] ?? null,
                    'original_connection' => $payload['original_connection'] ?? null,
                    'failed_at' => isset($payload['failed_at'])
                        ? \Carbon\Carbon::createFromTimestamp($payload['failed_at'])
                        : now(),
                ]);

                $js->ack($msg);
                $this->info('Stored DLQ message to failed_messages');
            } catch (Throwable $e) {
                $this->error('DLQ store error: '.$e->getMessage());
                \Illuminate\Support\Facades\Log::error('nats-chat:dlq-store error', [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}
