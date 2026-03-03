<?php

namespace App\Jobs;

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

    public function handle(): void
    {
        $start = microtime(true);
        $roomId = $this->payload['room_id'] ?? null;

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

            NatsStructuredLog::withDuration('notification.sent', 'ok', (microtime(true) - $start) * 1000, [
                'user_id' => $this->userId,
                'room_id' => $roomId,
            ]);
        } catch (Throwable $e) {
            NatsStructuredLog::error('notification.job.failed', 'error', $e, [
                'user_id' => $this->userId,
                'room_id' => $roomId,
                'attempt' => $this->attempts(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            throw $e;
        }
    }

    public function failed(?\Throwable $exception = null): void
    {
        NatsStructuredLog::error('notification.job.final_failure', 'failed', $exception ?? new \RuntimeException('Unknown'), [
            'user_id' => $this->userId,
        ]);
    }
}
