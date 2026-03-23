<?php

namespace App\Services\Chat;

use App\Models\Message;
use App\Models\Room;
use Illuminate\Support\Str;
use LaravelNats\Laravel\Facades\NatsV2;

/**
 * Publishes chat messages via the v2 stack (basis-company/nats + JSON envelope).
 * Envelope: id + type (NATS subject) + version + data; consumers use {@see \App\Support\EventPayload::unwrap()}.
 */
class ChatMessageService
{
    public function send(Room $room, int $userId, string $content): Message
    {
        $messageId = (string) Str::uuid();
        $timestamp = now();

        $data = [
            'message_id' => $messageId,
            'room_id' => $room->id,
            'user_id' => $userId,
            'content' => $content,
            'timestamp' => $timestamp->toIso8601String(),
        ];

        $subject = 'chat.room.'.$room->id.'.message';
        NatsV2::publish($subject, $data);

        \Log::info('Chat message published', ['subject' => $subject, 'message_id' => $messageId]);

        return Message::create([
            'message_id' => $messageId,
            'room_id' => $room->id,
            'user_id' => $userId,
            'content' => $content,
            'timestamp' => $timestamp,
        ]);
    }
}
