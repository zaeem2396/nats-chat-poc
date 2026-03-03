<?php

namespace App\Jobs;

use App\Services\MetricsService;
use App\Support\NatsStructuredLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class ModerateMessageJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public array $payload
    ) {}

    public function handle(MetricsService $metrics): void
    {
        $start = microtime(true);
        $messageId = $this->payload['message_id'] ?? null;
        $roomId = $this->payload['room_id'] ?? null;

        try {
            $messageId = $this->payload['message_id'] ?? null;
            if ($messageId && \Illuminate\Support\Facades\Cache::has('processed_message:' . $messageId)) {
                return; // idempotency: already processed
            }
            if ($this->attempts() > 1) {
                $metrics->incrementRetries();
            }
            $content = $this->payload['content'] ?? '';
            if (str_contains($content, 'fail-test')) {
                Log::warning('ModerateMessageJob: failing intentionally (fail-test)', ['payload' => $this->payload]);
                throw new \RuntimeException('Intentional fail for demo: message contains fail-test');
            }

            $durationMs = (microtime(true) - $start) * 1000;
            $metrics->incrementProcessed();
            $metrics->recordProcessingTime($durationMs);
            if ($messageId) {
                \Illuminate\Support\Facades\Cache::put('processed_message:' . $messageId, true, 86400);
            }
            NatsStructuredLog::withDuration('moderation.job.processed', 'ok', $durationMs, [
                'message_id' => $messageId,
                'room_id' => $roomId,
            ]);
        } catch (Throwable $e) {
            NatsStructuredLog::error('moderation.job.failed', 'error', $e, [
                'message_id' => $messageId,
                'room_id' => $roomId,
                'attempt' => $this->attempts(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            throw $e;
        }
    }

    public function failed(?\Throwable $exception = null): void
    {
        app(MetricsService::class)->incrementFailed();
        NatsStructuredLog::error('moderation.job.final_failure', 'failed', $exception ?? new \RuntimeException('Unknown'), [
            'message_id' => $this->payload['message_id'] ?? null,
            'room_id' => $this->payload['room_id'] ?? null,
        ]);
    }
}
