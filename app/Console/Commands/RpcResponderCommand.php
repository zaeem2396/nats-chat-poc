<?php

namespace App\Console\Commands;

use App\Nats\Rpc\UserPreferencesRpcResponder;
use Illuminate\Console\Command;

class RpcResponderCommand extends Command
{
    protected $signature = 'nats-chat:rpc-responder';

    protected $description = 'Run user.rpc.preferences RPC responder';

    public function handle(): int
    {
        $this->info('Starting RPC responder on user.rpc.preferences. Ctrl+C to stop.');

        (new UserPreferencesRpcResponder)->run();

        return self::SUCCESS;
    }
}
