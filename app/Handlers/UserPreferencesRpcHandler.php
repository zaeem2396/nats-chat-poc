<?php

namespace App\Handlers;

use Illuminate\Support\Facades\Log;
use LaravelNats\Contracts\Messaging\MessageHandlerInterface;
use LaravelNats\Contracts\Messaging\MessageInterface;
use LaravelNats\Laravel\Facades\Nats;

/**
 * Handler for nats:consume "user.rpc.preferences".
 * Responds to RPC requests with user notification preferences (reply-to pattern).
 */
class UserPreferencesRpcHandler implements MessageHandlerInterface
{
    public function handle(MessageInterface $message): void
    {
        $payload = $message->getDecodedPayload();
        Log::info('RPC request received', ['subject' => $message->getSubject(), 'payload' => $payload]);

        $replyTo = $message->getReplyTo();
        if (! $replyTo) {
            return;
        }

        $response = ['notifications_enabled' => true];
        Nats::connection()->publish($replyTo, $response);
        Log::info('RPC response sent', ['reply_to' => $replyTo]);
    }
}
