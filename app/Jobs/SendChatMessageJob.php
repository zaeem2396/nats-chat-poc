<?php

namespace App\Jobs;

use App\Models\Room;
use App\Services\Chat\ChatMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Delayed job: send a chat message at a scheduled time (JetStream delayed).
 */
class SendChatMessageJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public int $roomId,
        public int $userId,
        public string $content
    ) {}

    public function handle(ChatMessageService $chatMessageService): void
    {
        $room = Room::findOrFail($this->roomId);
        $chatMessageService->send($room, $this->userId, $this->content);
    }
}
