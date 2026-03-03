<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ModerateMessageJob implements ShouldQueue
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
            Log::warning('ModerateMessageJob: failing intentionally (fail-test)', ['payload' => $this->payload]);
            throw new \RuntimeException('Intentional fail for demo: message contains fail-test');
        }

        Log::info('Moderation job ran', ['message_id' => $this->payload['message_id'] ?? null]);
    }

    public function failed(?\Throwable $exception = null): void
    {
        Log::error('ModerateMessageJob failed', [
            'message_id' => $this->payload['message_id'] ?? null,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
