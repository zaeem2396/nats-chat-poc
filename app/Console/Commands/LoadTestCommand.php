<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Services\Chat\ChatMessageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Load test: send many messages via API (or in-process), measure time and success/failure.
 */
class LoadTestCommand extends Command
{
    protected $signature = 'nats-chat:load-test
                            {--count=100 : Number of messages to send}
                            {--use-http : Hit HTTP API instead of in-process}
                            {--base-url= : Base URL for API (e.g. http://localhost:8090)}';

    protected $description = 'Send messages and report success/failure counts and duration';

    public function handle(ChatMessageService $chatMessageService): int
    {
        $count = (int) $this->option('count');
        $useHttp = $this->option('use-http');
        $baseUrl = $this->option('base-url') ?: config('app.url');

        if ($count < 1 || $count > 10000) {
            $this->error('--count must be between 1 and 10000');
            return self::FAILURE;
        }

        $room = Room::first();
        if (! $room) {
            $room = Room::create(['name' => 'LoadTest']);
            $this->info('Created room LoadTest (id: '.$room->id.')');
        }

        $this->info("Sending {$count} messages " . ($useHttp ? "via HTTP to {$baseUrl}" : 'in-process') . '...');
        $start = microtime(true);
        $success = 0;
        $failed = 0;

        for ($i = 0; $i < $count; $i++) {
            try {
                if ($useHttp) {
                    $response = Http::timeout(10)->post("{$baseUrl}/api/rooms/{$room->id}/message", [
                        'user_id' => 1,
                        'content' => "Load test message #{$i}",
                    ]);
                    if ($response->successful()) {
                        $success++;
                    } else {
                        $failed++;
                    }
                } else {
                    $chatMessageService->send($room, 1, "Load test message #{$i}");
                    $success++;
                }
            } catch (\Throwable $e) {
                $failed++;
                if ($failed <= 3) {
                    $this->warn("Send failed: " . $e->getMessage());
                }
            }
        }

        $duration = microtime(true) - $start;
        $this->newLine();
        $this->info('--- Load test result ---');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total', $count],
                ['Success', $success],
                ['Failed', $failed],
                ['Duration (s)', round($duration, 2)],
                ['Throughput (msg/s)', $duration > 0 ? round($success / $duration, 1) : 0],
            ]
        );
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
