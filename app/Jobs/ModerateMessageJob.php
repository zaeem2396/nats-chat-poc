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
        $subject = 'queue.default';
        $messageId = $this->payload['message_id'] ?? null;
        $roomId = $this->payload['room_id'] ?? null;
        $attempt = $this->attempts();

        $metrics->incrementTotalMessages();
        if ($messageId && \Illuminate\Support\Facades\Cache::has('processed_message:' . $messageId)) {
            return; // idempotency: already processed
        }
        if ($attempt > 1) {
            $metrics->incrementRetries();
            NatsStructuredLog::messageProcessed(
                'moderation.job.retry',
                $subject,
                NatsStructuredLog::STATUS_RETRY,
                $attempt,
                (microtime(true) - $start) * 1000,
                null,
            );
        }

        try {
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
            NatsStructuredLog::messageProcessed(
                'moderation.job.processed',
                $subject,
                NatsStructuredLog::STATUS_SUCCESS,
                $attempt,
                $durationMs,
                null,
            );
        } catch (Throwable $e) {
            $durationMs = (microtime(true) - $start) * 1000;
            NatsStructuredLog::messageProcessed(
                'moderation.job.failed',
                $subject,
                NatsStructuredLog::STATUS_FAILED,
                $attempt,
                $durationMs,
                $e->getMessage(),
            );
            throw $e;
        }
    }

    public function failed(?\Throwable $exception = null): void
    {
        app(MetricsService::class)->incrementFailed();
        NatsStructuredLog::messageProcessed(
            'moderation.job.final_failure',
            'queue.default',
            NatsStructuredLog::STATUS_FAILED,
            $this->attempts(),
            0,
            $exception?->getMessage(),
        );
    }
}
