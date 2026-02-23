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

        $analytic = Analytic::firstOrCreate(
            ['room_id' => $roomId],
            ['message_count' => 0]
        );
        $analytic->increment('message_count');

        Log::info('Analytics incremented', ['room_id' => $roomId]);
    }
}
