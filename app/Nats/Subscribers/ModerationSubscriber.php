<?php

namespace App\Nats\Subscribers;

use App\Jobs\ModerateMessageJob;
use App\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Log;
use LaravelNats\Contracts\Messaging\MessageInterface;
use LaravelNats\Laravel\Facades\Nats;

/**
 * Subscribes to chat.room.*.message (single-level wildcard).
 * Dispatches ModerateMessageJob and SendNotificationJob to NATS queue.
 */
class ModerationSubscriber
{
    private const SUBJECT = 'chat.room.*.message'; // config('nats_subjects.chat.room_message_wildcard')

    public function run(): void
    {
        $sid = Nats::subscribe(self::SUBJECT, function (MessageInterface $message): void {
            $payload = $message->getDecodedPayload();
            Log::info('Moderation received message', ['subject' => $message->getSubject(), 'message_id' => $payload['message_id'] ?? null]);

            ModerateMessageJob::dispatch($payload);
            $userId = $payload['user_id'] ?? 0;
            SendNotificationJob::dispatch($userId, $payload);
        });

        Log::info('Moderation subscribed', ['subject' => self::SUBJECT, 'sid' => $sid]);
        while (true) {
            Nats::process(1.0);
        }
    }
}
