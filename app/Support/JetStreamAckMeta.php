<?php

namespace App\Support;

use Basis\Nats\Message\Msg;

/**
 * Reads JetStream pull metadata from the $JS.ACK reply subject.
 *
 * New format (NATS 2.9+):
 * $JS.ACK.<domain>.<account_hash>.<stream>.<consumer>.<num_redeliveries>.<stream_seq>.<consumer_seq>.<timestamp>.<pending>.<random>
 *
 * @see https://docs.nats.io/nats-concepts/jetstream/consumers#acknowledging-messages
 */
final class JetStreamAckMeta
{
    /**
     * 1-based delivery attempt (first delivery => 1).
     */
    public static function deliveryAttempt(Msg $msg): int
    {
        $replyTo = $msg->replyTo ?? '';
        if ($replyTo === '' || ! str_starts_with($replyTo, '$JS.ACK')) {
            return 1;
        }

        $tokens = explode('.', $replyTo);
        if (count($tokens) === 9) {
            array_splice($tokens, 2, 0, ['', '']);
        }

        if (count($tokens) < 11) {
            return 1;
        }

        $numRedeliveries = (int) $tokens[6];

        return max(1, $numRedeliveries + 1);
    }

    public static function maxDeliverForConsumer(string $logicalKey): int
    {
        /** @var array<string, mixed> $block */
        $block = config("nats_orders.consumers.{$logicalKey}", []);

        return max(1, (int) ($block['max_deliver'] ?? 5));
    }

    public static function ackWaitSecondsForConsumer(string $logicalKey): int
    {
        /** @var array<string, mixed> $block */
        $block = config("nats_orders.consumers.{$logicalKey}", []);

        return max(1, (int) ($block['ack_wait_seconds'] ?? 30));
    }
}
