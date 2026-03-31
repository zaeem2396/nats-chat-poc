<?php

namespace App\Services\Nats;

use App\Contracts\OrderStreamProvisioner;
use Basis\Nats\Consumer\AckPolicy;
use LaravelNats\Laravel\Facades\NatsV2;

/**
 * JetStream consumer provisioning (explicit, readable server-side retry policy).
 *
 * For each logical consumer key in config('nats_orders.consumers.*') this class:
 *
 * 1. Ensures the ORDERS stream exists (preset merge from OrderMessagingServiceProvider).
 * 2. Creates the durable consumer if missing, with:
 *    - Deliver policy: default (all messages matching filter).
 *    - Ack policy: EXPLICIT — messages are redelivered until ack() or term(), or until ack_wait elapses.
 *    - ack_wait: consumer config `ack_wait_seconds` (converted to nanoseconds for the server).
 *    - max_deliver: hard cap on delivery attempts; after this, JetStream stops redelivering — application
 *      code should detect the final attempt (see JetStreamAckMeta::deliveryAttempt) and move to DLQ if needed.
 *    - Filter: single `filter_subject` or multiple `filter_subjects` for notifications.
 *
 * Changing ack_wait / max_deliver after a consumer exists requires deleting/recreating the consumer in NATS
 * (or using a new durable name) — the server does not mutate these on our idempotent `exists()` skip path.
 *
 * @see config/nats_orders.php
 * @see \App\Support\JetStreamAckMeta
 */
final class OrderStreamConsumerProvisioner implements OrderStreamProvisioner
{
    /**
     * Create the ORDERS JetStream stream if missing (fresh NATS volume / first deploy).
     */
    public function ensureStreamExists(): void
    {
        $manager = NatsV2::jetstream();
        $streamName = (string) config('nats_orders.stream_name', 'ORDERS');
        $jsStream = $manager->stream($streamName);

        if ($jsStream->exists()) {
            return;
        }

        NatsV2::jetStreamProvisionPreset(
            (string) config('nats_orders.stream_preset_key', 'order_processing'),
            true,
            null,
        );
    }

    public function ensure(string $logicalKey): void
    {
        $this->ensureStreamExists();

        /** @var array<string, mixed> $block */
        $block = config("nats_orders.consumers.{$logicalKey}", []);
        $stream = (string) config('nats_orders.stream_name', 'ORDERS');
        $durable = (string) ($block['durable_name'] ?? '');
        if ($durable === '') {
            throw new \InvalidArgumentException("Missing durable_name for consumer [{$logicalKey}].");
        }

        $manager = NatsV2::jetstream();
        $jsStream = $manager->stream($stream);
        $consumer = $jsStream->getConsumer($durable);

        if ($consumer->exists()) {
            return;
        }

        $cfg = $consumer->getConfiguration();
        if (! empty($block['filter_subjects']) && is_array($block['filter_subjects'])) {
            /** @var list<string> $filters */
            $filters = array_values(array_filter($block['filter_subjects'], static fn ($s): bool => is_string($s) && $s !== ''));
            $cfg->setSubjectFilters($filters);
        } else {
            $filter = (string) ($block['filter_subject'] ?? '');
            if ($filter === '') {
                throw new \InvalidArgumentException("Missing filter for consumer [{$logicalKey}].");
            }
            $cfg->setSubjectFilter($filter);
        }

        $cfg->setAckPolicy(AckPolicy::EXPLICIT);
        $ackSec = (int) ($block['ack_wait_seconds'] ?? 30);
        $cfg->setAckWait(max(1, $ackSec) * 1_000_000_000);
        $cfg->setMaxDeliver(max(1, (int) ($block['max_deliver'] ?? 5)));

        $consumer->create();
    }
}
