<?php

namespace App\Handlers;

use App\Jobs\ModerateMessageJob;
use App\Jobs\SendNotificationJob;
use App\Support\NatsStructuredLog;
use LaravelNats\Contracts\Messaging\MessageHandlerInterface;
use LaravelNats\Contracts\Messaging\MessageInterface;
use Throwable;

/**
 * Handler for nats:consume "chat.room.*.message".
 * Dispatches ModerateMessageJob and SendNotificationJob to the NATS queue.
 */
class ModerationMessageHandler implements MessageHandlerInterface
{
    public function handle(MessageInterface $message): void
    {
        $start = microtime(true);
        $payload = $message->getDecodedPayload();
        $roomId = $payload['room_id'] ?? null;
        $messageId = $payload['message_id'] ?? null;

        try {
            NatsStructuredLog::event('moderation.message.received', 'processing', [
                'subject' => $message->getSubject(),
                'room_id' => $roomId,
                'message_id' => $messageId,
            ]);

            ModerateMessageJob::dispatch($payload);
            $userId = $payload['user_id'] ?? 0;
            SendNotificationJob::dispatch($userId, $payload);

            NatsStructuredLog::withDuration('moderation.message.dispatched', 'ok', (microtime(true) - $start) * 1000, [
                'room_id' => $roomId,
                'message_id' => $messageId,
            ]);
        } catch (Throwable $e) {
            NatsStructuredLog::error('moderation.message.failed', 'error', $e, [
                'room_id' => $roomId,
                'message_id' => $messageId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            throw $e;
        }
    }
}
