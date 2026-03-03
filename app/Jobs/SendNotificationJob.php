<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use LaravelNats\Laravel\Facades\Nats;

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
        $content = $this->payload['content'] ?? '';
        if (str_contains($content, 'fail-test')) {
            Log::warning('SendNotificationJob: failing intentionally (fail-test)', ['payload' => $this->payload]);
            throw new \RuntimeException('Intentional fail for demo: message contains fail-test');
        }

        // RPC: check user preferences before sending
        try {
            $response = Nats::request('user.rpc.preferences', ['user_id' => $this->userId], timeout: 3.0);
            $prefs = $response->getDecodedPayload();
            Log::info('RPC response received', ['preferences' => $prefs]);
            if (isset($prefs['notifications_enabled']) && $prefs['notifications_enabled'] !== true) {
                Log::info('Notifications disabled for user, skipping', ['user_id' => $this->userId]);

                return;
            }
        } catch (\Throwable $e) {
            Log::warning('RPC preferences failed, skipping notification', ['error' => $e->getMessage()]);
        }

        Log::info('Notification sent (email)', ['user_id' => $this->userId, 'subject' => 'notifications.email']);
        // In a real app: Mail::to(...)->send(...);
    }

    public function failed(?\Throwable $exception = null): void
    {
        Log::error('SendNotificationJob failed', [
            'user_id' => $this->userId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
