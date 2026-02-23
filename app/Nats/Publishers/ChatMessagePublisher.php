<?php

namespace App\Nats\Publishers;

use LaravelNats\Laravel\Facades\Nats;

/**
 * NATS publisher for chat messages.
 * Uses default connection; subject pattern chat.room.{roomId}.message
 */
class ChatMessagePublisher
{
    public function publish(int $roomId, array $payload): void
    {
        $subject = 'chat.room.' . $roomId . '.message';
        Nats::publish($subject, $payload);
    }
}
