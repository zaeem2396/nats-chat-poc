<?php

namespace App\Handlers;

use App\Support\NatsStructuredLog;
use LaravelNats\Contracts\Messaging\MessageHandlerInterface;
use LaravelNats\Contracts\Messaging\MessageInterface;
use LaravelNats\Laravel\Facades\Nats;
use Throwable;

/**
 * Handler for nats:consume "user.rpc.preferences".
 * Responds to RPC requests with user notification preferences (reply-to pattern).
 */
class UserPreferencesRpcHandler implements MessageHandlerInterface
{
    public function handle(MessageInterface $message): void
    {
        $start = microtime(true);
        $replyTo = $message->getReplyTo();

        try {
            $payload = $message->getDecodedPayload();
            NatsStructuredLog::event('rpc.preferences.received', 'processing', [
                'subject' => $message->getSubject(),
                'reply_to' => $replyTo,
            ]);

            if (! $replyTo) {
                NatsStructuredLog::event('rpc.preferences.skipped', 'no_reply_to', []);
                return;
            }

            $response = ['notifications_enabled' => true];
            Nats::connection()->publish($replyTo, $response);

            NatsStructuredLog::withDuration('rpc.preferences.sent', 'ok', (microtime(true) - $start) * 1000, [
                'reply_to' => $replyTo,
            ]);
        } catch (Throwable $e) {
            NatsStructuredLog::error('rpc.preferences.failed', 'error', $e, [
                'reply_to' => $replyTo,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            throw $e;
        }
    }
}
