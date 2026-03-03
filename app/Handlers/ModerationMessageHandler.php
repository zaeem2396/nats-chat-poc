<?php

namespace App\Handlers;

use App\Jobs\ModerateMessageJob;
use App\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Log;
use LaravelNats\Contracts\Messaging\MessageHandlerInterface;
use LaravelNats\Contracts\Messaging\MessageInterface;

/**
 * Handler for nats:consume "chat.room.*.message".
 * Dispatches ModerateMessageJob and SendNotificationJob to the NATS queue.
 */
class ModerationMessageHandler implements MessageHandlerInterface
{
    public function handle(MessageInterface $message): void
    {
        $payload = $message->getDecodedPayload();
        Log::info('Moderation received message', [
            'subject' => $message->getSubject(),
            'message_id' => $payload['message_id'] ?? null,
        ]);

        ModerateMessageJob::dispatch($payload);
        $userId = $payload['user_id'] ?? 0;
        SendNotificationJob::dispatch($userId, $payload);
    }
}
