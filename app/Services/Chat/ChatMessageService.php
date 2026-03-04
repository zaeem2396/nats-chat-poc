<?php

namespace App\Services\Chat;

use App\Models\Message;
use App\Models\Room;
use Illuminate\Support\Str;
use LaravelNats\Laravel\Facades\Nats;

/**
 * Publishes chat messages to NATS (versioned payload) and persists to DB.
 * Payload: { version: "v1", type: "chat.message.created", data: { ... } }
 */
class ChatMessageService
{
    public const EVENT_TYPE = 'chat.message.created';

    public const VERSION = 'v1';

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

        $payload = [
            'id' => $messageId,
            'type' => self::EVENT_TYPE,
            'version' => self::VERSION,
            'data' => $data,
        ];

        $subject = 'chat.room.'.$room->id.'.message';
        Nats::publish($subject, $payload);

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
