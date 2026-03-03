<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAnalyticsJob;
use App\Services\JetStream\ChatStreamBootstrap;
use App\Support\NatsStructuredLog;
use Illuminate\Console\Command;
use LaravelNats\Laravel\Facades\Nats;
use Throwable;

/**
 * Long-running worker: fetches from JetStream durable consumer "analytics-service",
 * dispatches ProcessAnalyticsJob to queue, then acks. Resume from last on restart.
 */
class AnalyticsWorkerCommand extends Command
{
    protected $signature = 'nats-chat:analytics-worker
                            {--connection=analytics : NATS connection name}
                            {--timeout=5 : Fetch timeout in seconds}
                            {--no-wait : Return immediately when no message}';

    protected $description = 'Run JetStream analytics consumer (durable: analytics-service)';

    public function handle(): int
    {
        $connectionName = $this->option('connection');
        $timeout = (float) $this->option('timeout');
        $noWait = $this->option('no-wait');

        $this->info('Bootstrapping chat stream and consumer...');
        (new ChatStreamBootstrap)->ensureStreamAndConsumer();

        $js = Nats::jetstream($connectionName);
        if (! $js->isAvailable()) {
            $this->error('JetStream not available on connection: '.$connectionName);

            return self::FAILURE;
        }

        $streamName = ChatStreamBootstrap::STREAM_NAME;
        $consumerName = ChatStreamBootstrap::CONSUMER_NAME;

        $this->info("Consuming from {$streamName} / {$consumerName} (connection: {$connectionName}). Ctrl+C to stop.");

        while (true) {
            try {
                $msg = $js->fetchNextMessage($streamName, $consumerName, $timeout, $noWait);
                if ($msg === null) {
                    continue;
                }

                $raw = json_decode($msg->getPayload(), true);
                if (is_array($raw)) {
                    $payload = \App\Support\EventPayload::unwrap($raw);
                    $roomId = $payload['room_id'] ?? null;
                    ProcessAnalyticsJob::dispatch($payload);
                    NatsStructuredLog::event('analytics.worker.dispatched', 'ok', [
                        'room_id' => $roomId,
                        'stream' => $streamName,
                    ]);
                    $this->info('Dispatched ProcessAnalyticsJob for message');
                }

                $js->ack($msg);
            } catch (Throwable $e) {
                $this->error('Error: '.$e->getMessage());
                NatsStructuredLog::error('analytics.worker.error', 'error', $e, [
                    'stream' => $streamName,
                    'consumer' => $consumerName,
                ]);
            }
        }
    }
}
