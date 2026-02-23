<?php

namespace App\Services\Chat;

use App\Models\Message;
use App\Models\Room;
use Illuminate\Support\Str;
use LaravelNats\Laravel\Facades\Nats;

/**
 * Publishes chat messages to NATS and persists to DB.
 * Subject: chat.room.{roomId}.message
 */
class ChatMessageService
{
    public function send(Room $room, int $userId, string $content): Message
    {
        $messageId = (string) Str::uuid();
        $timestamp = now();

        $payload = [
            'message_id' => $messageId,
            'room_id' => $room->id,
            'user_id' => $userId,
            'content' => $content,
            'timestamp' => $timestamp->toIso8601String(),
        ];

        $subject = 'chat.room.' . $room->id . '.message';
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
