<?php

namespace App\Services\JetStream;

use Illuminate\Support\Facades\Log;
use LaravelNats\Core\JetStream\ConsumerConfig;
use LaravelNats\Core\JetStream\StreamConfig;
use LaravelNats\Laravel\Facades\Nats;

/**
 * Ensures JetStream stream "chat-stream" (subject chat.room.>)
 * and durable consumer "analytics-service" exist.
 */
class ChatStreamBootstrap
{
    public const STREAM_NAME = 'chat-stream';

    public const CONSUMER_NAME = 'analytics-service';

    public const SUBJECTS = ['chat.room.>'];

    public function ensureStreamAndConsumer(): void
    {
        $js = Nats::jetstream();
        if (! $js->isAvailable()) {
            Log::warning('JetStream not available, skipping chat stream bootstrap');

            return;
        }

        try {
            $js->getStreamInfo(self::STREAM_NAME);
            Log::info('JetStream stream already exists', ['stream' => self::STREAM_NAME]);
        } catch (\Throwable) {
            $config = (new StreamConfig(self::STREAM_NAME, self::SUBJECTS))
                ->withStorage(StreamConfig::STORAGE_FILE)
                ->withMaxMessages(100_000);
            $js->createStream($config);
            Log::info('JetStream stream created', ['stream' => self::STREAM_NAME]);
        }

        try {
            $js->getConsumerInfo(self::STREAM_NAME, self::CONSUMER_NAME);
            Log::info('JetStream consumer already exists', ['consumer' => self::CONSUMER_NAME]);
        } catch (\Throwable) {
            $consumerConfig = (new ConsumerConfig(self::CONSUMER_NAME))
                ->withFilterSubject('chat.room.>')
                ->withDeliverPolicy(ConsumerConfig::DELIVER_ALL)
                ->withAckPolicy(ConsumerConfig::ACK_EXPLICIT)
                ->withAckWait(config('nats.jetstream.consumer.ack_wait', 30.0))
                ->withMaxDeliver(config('nats.jetstream.consumer.max_deliver', 3));
            $js->createConsumer(self::STREAM_NAME, self::CONSUMER_NAME, $consumerConfig);
            Log::info('JetStream consumer created', [
                'consumer' => self::CONSUMER_NAME,
                'ack_wait' => $consumerConfig->getAckWait(),
                'max_deliver' => $consumerConfig->getMaxDeliver(),
            ]);
        }
    }
}
