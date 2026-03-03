<?php

namespace App\Jobs;

use App\Models\Analytic;
use App\Models\Room;
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

    public function handle(): void
    {
        $start = microtime(true);
        $roomId = $this->payload['room_id'] ?? null;

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

            NatsStructuredLog::withDuration('analytics.job.processed', 'ok', (microtime(true) - $start) * 1000, [
                'room_id' => $roomId,
            ]);
        } catch (\Throwable $e) {
            NatsStructuredLog::error('analytics.job.failed', 'error', $e, [
                'room_id' => $roomId,
                'attempt' => $this->attempts(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            throw $e;
        }
    }

    public function failed(?\Throwable $exception = null): void
    {
        NatsStructuredLog::error('analytics.job.final_failure', 'failed', $exception ?? new \RuntimeException('Unknown'), [
            'room_id' => $this->payload['room_id'] ?? null,
        ]);
    }
}
