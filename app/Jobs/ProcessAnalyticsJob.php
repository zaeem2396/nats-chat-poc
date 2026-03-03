<?php

namespace App\Jobs;

use App\Models\Analytic;
use App\Models\Room;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
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
        $content = $this->payload['content'] ?? '';
        if (str_contains($content, 'fail-test')) {
            Log::warning('ProcessAnalyticsJob: failing intentionally (fail-test)', ['payload' => $this->payload]);
            throw new \RuntimeException('Intentional fail for demo: message contains fail-test');
        }

        $roomId = $this->payload['room_id'] ?? null;
        if (! $roomId) {
            return;
        }

        $room = Room::find($roomId);
        if (! $room) {
            return;
        }

        Analytic::query()->updateOrInsert(
            ['room_id' => $roomId],
            ['message_count' => DB::raw('message_count + 1'), 'updated_at' => now()]
        );

        Log::info('Analytics incremented', ['room_id' => $roomId]);
    }

    public function failed(?\Throwable $exception = null): void
    {
        Log::error('ProcessAnalyticsJob failed', [
            'room_id' => $this->payload['room_id'] ?? null,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
