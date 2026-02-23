<?php

namespace App\Console\Commands;

use App\Nats\Subscribers\ModerationSubscriber;
use Illuminate\Console\Command;

class ModerationSubscriberCommand extends Command
{
    protected $signature = 'nats-chat:moderation {--once : Process one batch then exit}';

    protected $description = 'Run moderation subscriber (chat.room.*.message) and dispatch jobs';

    public function handle(): int
    {
        $this->info('Starting moderation subscriber on chat.room.*.message. Ctrl+C to stop.');

        (new ModerationSubscriber)->run();

        return self::SUCCESS;
    }
}
