<?php

namespace App\Services\JetStream;

use Illuminate\Support\Facades\Log;
use LaravelNats\Core\JetStream\ConsumerConfig;
use LaravelNats\Core\JetStream\StreamConfig;
use LaravelNats\Laravel\Facades\Nats;

/**
 * Ensures JetStream stream "dlq" (subject chat.dlq) and durable consumer exist.
 * Failed queue jobs are published to chat.dlq and stored here for persistence.
 */
class DlqStreamBootstrap
{
    public const STREAM_NAME = 'dlq';

    public const CONSUMER_NAME = 'dlq_store';

    public const SUBJECT = 'chat.dlq';

    public function ensureStreamAndConsumer(): void
    {
        $js = Nats::jetstream();
        if (! $js->isAvailable()) {
            Log::warning('JetStream not available, skipping DLQ stream bootstrap');

            return;
        }

        try {
            $js->getStreamInfo(self::STREAM_NAME);
            Log::info('JetStream DLQ stream already exists', ['stream' => self::STREAM_NAME]);
        } catch (\Throwable) {
            $config = (new StreamConfig(self::STREAM_NAME, [self::SUBJECT]))
                ->withStorage(StreamConfig::STORAGE_FILE)
                ->withMaxMessages(50_000);
            $js->createStream($config);
            Log::info('JetStream DLQ stream created', ['stream' => self::STREAM_NAME]);
        }

        try {
            $js->getConsumerInfo(self::STREAM_NAME, self::CONSUMER_NAME);
            Log::info('JetStream DLQ consumer already exists', ['consumer' => self::CONSUMER_NAME]);
        } catch (\Throwable) {
            $consumerConfig = (new ConsumerConfig(self::CONSUMER_NAME))
                ->withFilterSubject(self::SUBJECT)
                ->withDeliverPolicy(ConsumerConfig::DELIVER_ALL)
                ->withAckPolicy(ConsumerConfig::ACK_EXPLICIT)
                ->withAckWait(config('nats.jetstream.consumer.ack_wait', 30.0))
                ->withMaxDeliver(config('nats.jetstream.consumer.max_deliver', 3));
            $js->createConsumer(self::STREAM_NAME, self::CONSUMER_NAME, $consumerConfig);
            Log::info('JetStream DLQ consumer created', ['consumer' => self::CONSUMER_NAME]);
        }
    }
}
