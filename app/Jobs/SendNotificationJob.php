<?php

namespace App\Jobs;

use App\Services\MetricsService;
use App\Support\NatsStructuredLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use LaravelNats\Laravel\Facades\Nats;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public int $userId,
        public array $payload
    ) {}

    public function handle(MetricsService $metrics): void
    {
        $start = microtime(true);
        $subject = 'queue.default';
        $roomId = $this->payload['room_id'] ?? null;
        $attempt = $this->attempts();

        $metrics->incrementTotalMessages();
        if ($attempt > 1) {
            $metrics->incrementRetries();
            NatsStructuredLog::messageProcessed(
                'notification.job.retry',
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
                Log::warning('SendNotificationJob: failing intentionally (fail-test)', ['payload' => $this->payload]);
                throw new \RuntimeException('Intentional fail for demo: message contains fail-test');
            }

            try {
                $response = Nats::request('user.rpc.preferences', ['user_id' => $this->userId], timeout: 3.0);
                $prefs = $response->getDecodedPayload();
                if (isset($prefs['notifications_enabled']) && $prefs['notifications_enabled'] !== true) {
                    NatsStructuredLog::event('notification.skipped', 'disabled', ['user_id' => $this->userId]);
                    return;
                }
            } catch (Throwable $e) {
                Log::warning('RPC preferences failed, skipping notification', ['error' => $e->getMessage()]);
            }

            $durationMs = (microtime(true) - $start) * 1000;
            $metrics->incrementProcessed();
            $metrics->recordProcessingTime($durationMs);
            NatsStructuredLog::messageProcessed(
                'notification.sent',
                $subject,
                NatsStructuredLog::STATUS_SUCCESS,
                $attempt,
                $durationMs,
                null,
            );
        } catch (Throwable $e) {
            $durationMs = (microtime(true) - $start) * 1000;
            NatsStructuredLog::messageProcessed(
                'notification.job.failed',
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
            'notification.job.final_failure',
            'queue.default',
            NatsStructuredLog::STATUS_FAILED,
            $this->attempts(),
            0,
            $exception?->getMessage(),
        );
    }
}
