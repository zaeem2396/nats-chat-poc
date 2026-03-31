<?php

namespace App\Services\Nats;

use App\Contracts\OrderStreamProvisioner;
use Basis\Nats\Consumer\AckPolicy;
use LaravelNats\Laravel\Facades\NatsV2;

/**
 * Creates durable JetStream pull consumers with explicit ack, filters, and max deliver.
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
        $ackSec = (int) ($block['ack_wait_seconds'] ?? 120);
        $cfg->setAckWait(max(1, $ackSec) * 1_000_000_000);
        $cfg->setMaxDeliver(max(1, (int) ($block['max_deliver'] ?? 10)));

        $consumer->create();
    }
}
