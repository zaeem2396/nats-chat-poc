<?php

namespace App\Services\Nats;

use App\Contracts\OrderStreamProvisioner;
use Illuminate\Support\Facades\Log;
use LaravelNats\Laravel\Facades\NatsV2;

/**
 * Long-lived pull loop for order pipeline workers (graceful SIGINT/SIGTERM when pcntl available).
 */
final class JetStreamOrderWorkerLoop
{
    private bool $running = true;

    public function __construct(
        private readonly OrderStreamProvisioner $provisioner,
    ) {}

    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * @param  callable(\Basis\Nats\Message\Msg): void  $handler  Must ack(), nack(), or term() each message.
     */
    public function run(string $logicalKey, callable $handler): int
    {
        $this->provisioner->ensure($logicalKey);
        $this->registerSignals();

        $stream = (string) config('nats_orders.stream_name', 'ORDERS');
        /** @var array<string, mixed> $block */
        $block = config("nats_orders.consumers.{$logicalKey}", []);
        $durable = (string) ($block['durable_name'] ?? '');
        $batch = (int) config('nats_orders.pull_batch', 8);
        $expires = (float) config('nats_orders.pull_expires_seconds', 0.75);

        $processed = 0;
        while ($this->running) {
            try {
                $messages = NatsV2::jetStreamPull($stream, $durable, $batch, $expires);
                foreach ($messages as $msg) {
                    // Pull batches can include non-user frames (e.g. empty 404) with no $JS.ACK reply subject.
                    if ($msg->replyTo === null || $msg->replyTo === '') {
                        Log::debug('order_pipeline.worker_loop.skip_non_ackable_message', [
                            'logical_key' => $logicalKey,
                            'subject' => $msg->subject,
                        ]);

                        continue;
                    }
                    $handler($msg);
                    $processed++;
                }
                NatsV2::connection()->process(0.02);
            } catch (\ErrorException $e) {
                // basis-company/nats uses stream_select(); async signals (pcntl) can interrupt with EINTR.
                if (str_contains($e->getMessage(), 'Interrupted system call')) {
                    if (! $this->running) {
                        break;
                    }

                    continue;
                }
                throw $e;
            }
        }

        return $processed;
    }

    private function registerSignals(): void
    {
        if (! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        $loop = $this;
        pcntl_signal(SIGINT, static function () use ($loop): void {
            $loop->stop();
        });
        pcntl_signal(SIGTERM, static function () use ($loop): void {
            $loop->stop();
        });
    }
}
