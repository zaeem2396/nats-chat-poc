<?php

namespace App\Nats\Rpc;

use LaravelNats\Contracts\Messaging\MessageInterface;
use LaravelNats\Laravel\Facades\Nats;

/**
 * Responds to user.rpc.preferences requests with notification preferences.
 * Run in a long-lived process; subscribes and replies to each request.
 */
class UserPreferencesRpcResponder
{
    private const SUBJECT = 'user.rpc.preferences';

    public function run(): void
    {
        $sid = Nats::subscribe(self::SUBJECT, function (MessageInterface $message): void {
            $payload = $message->getDecodedPayload();
            \Log::info('RPC request received', ['subject' => self::SUBJECT, 'payload' => $payload]);

            $replyTo = $message->getReplyTo();
            if (! $replyTo) {
                return;
            }

            $response = ['notifications_enabled' => true];
            Nats::connection()->publish($replyTo, $response);
            \Log::info('RPC response sent', ['reply_to' => $replyTo]);
        });

        \Log::info('RPC responder subscribed', ['subject' => self::SUBJECT, 'sid' => $sid]);
        while (true) {
            Nats::process(1.0);
        }
    }
}
