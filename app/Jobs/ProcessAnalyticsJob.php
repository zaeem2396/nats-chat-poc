<?php

namespace App\Jobs;

use App\Models\Analytic;
use App\Models\Room;
use App\Services\MetricsService;
use App\Support\NatsStructuredLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAnalyticsJob implements ShouldQueue
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
        $roomId = $this->payload['room_id'] ?? null;
        $messageId = $this->payload['message_id'] ?? null;
        $attempt = $this->attempts();

        $metrics->incrementTotalMessages();
        if ($messageId && \Illuminate\Support\Facades\Cache::has('processed_analytics:' . $messageId)) {
            return; // idempotency
        }
        if ($attempt > 1) {
            $metrics->incrementRetries();
            NatsStructuredLog::messageProcessed(
                'analytics.job.retry',
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
                Log::warning('ProcessAnalyticsJob: failing intentionally (fail-test)', ['payload' => $this->payload]);
                throw new \RuntimeException('Intentional fail for demo: message contains fail-test');
            }

            if (! $roomId) {
                NatsStructuredLog::event('analytics.job.skipped', 'no_room_id', ['payload_keys' => array_keys($this->payload)]);
                return;
            }

            $room = Room::find($roomId);
            if (! $room) {
                NatsStructuredLog::event('analytics.job.skipped', 'room_not_found', ['room_id' => $roomId]);
                return;
            }

            Analytic::query()->updateOrInsert(
                ['room_id' => $roomId],
                ['message_count' => DB::raw('message_count + 1'), 'updated_at' => now()]
            );

            $durationMs = (microtime(true) - $start) * 1000;
            $metrics->incrementProcessed();
            $metrics->recordProcessingTime($durationMs);
            if ($messageId) {
                \Illuminate\Support\Facades\Cache::put('processed_analytics:' . $messageId, true, 86400);
            }
            NatsStructuredLog::messageProcessed(
                'analytics.job.processed',
                $subject,
                NatsStructuredLog::STATUS_SUCCESS,
                $attempt,
                $durationMs,
                null,
            );
        } catch (\Throwable $e) {
            $durationMs = (microtime(true) - $start) * 1000;
            NatsStructuredLog::messageProcessed(
                'analytics.job.failed',
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
            'analytics.job.final_failure',
            'queue.default',
            NatsStructuredLog::STATUS_FAILED,
            $this->attempts(),
            0,
            $exception?->getMessage(),
        );
    }
}
