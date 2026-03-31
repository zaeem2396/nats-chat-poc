<?php

declare(strict_types=1);

/**
 * Order pipeline: JetStream stream, subjects, durable consumers, retry/DLQ tuning.
 *
 * JetStream reliability (visible to operators):
 * - ack_wait_seconds: how long before an unacked message is redelivered.
 * - max_deliver: max delivery attempts per message; after that the server stops redelivering —
 *   handlers should move poison messages to DLQ on the final attempt when business logic fails.
 *
 * @see README.md section "Failure Handling & Reliability"
 * @see App\Services\Nats\OrderStreamConsumerProvisioner
 */
return [

    'stream_name' => env('NATS_ORDERS_STREAM', 'ORDERS'),

    /*
    |--------------------------------------------------------------------------
    | JetStream preset key (merged into nats_basis.jetstream.presets at boot)
    |--------------------------------------------------------------------------
    */
    'stream_preset_key' => 'order_processing',

    /*
    |--------------------------------------------------------------------------
    | Subjects (must match stream capture; use * single token per segment)
    |--------------------------------------------------------------------------
    */
    'subjects' => [
        'orders_created' => env('NATS_SUBJECT_ORDERS_CREATED', 'orders.created'),
        'payments_completed' => env('NATS_SUBJECT_PAYMENTS_COMPLETED', 'payments.completed'),
        'payments_failed' => env('NATS_SUBJECT_PAYMENTS_FAILED', 'payments.failed'),
        'inventory_updated' => env('NATS_SUBJECT_INVENTORY_UPDATED', 'inventory.updated'),
        'orders_dlq' => env('NATS_SUBJECT_ORDERS_DLQ', 'orders.dlq'),
        'payments_dlq' => env('NATS_SUBJECT_PAYMENTS_DLQ', 'payments.dlq'),
        'inventory_dlq' => env('NATS_SUBJECT_INVENTORY_DLQ', 'inventory.dlq'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Durable pull consumers (EXPLICIT ack policy; filter per logical service)
    |--------------------------------------------------------------------------
    | Each block is applied in OrderStreamConsumerProvisioner::ensure():
    |   - AckPolicy::EXPLICIT
    |   - ack_wait_seconds → nanoseconds for JetStream consumer ack_wait
    |   - max_deliver     → JetStream max_deliver (hard cap on redeliveries)
    |--------------------------------------------------------------------------
    */
    'consumers' => [
        'orders' => [
            'durable_name' => env('NATS_CONSUMER_ORDERS', 'svc_orders_order_created'),
            'filter_subject' => 'orders.created',
            'ack_wait_seconds' => (int) env('NATS_CONSUMER_ORDERS_ACK_WAIT', env('NATS_CONSUMER_ACK_WAIT', 30)),
            'max_deliver' => (int) env('NATS_CONSUMER_ORDERS_MAX_DELIVER', env('NATS_CONSUMER_MAX_DELIVER', 5)),
        ],
        'payments' => [
            'durable_name' => env('NATS_CONSUMER_PAYMENTS', 'svc_payments_order_created'),
            'filter_subject' => 'orders.created',
            'ack_wait_seconds' => (int) env('NATS_CONSUMER_PAYMENTS_ACK_WAIT', env('NATS_CONSUMER_ACK_WAIT', 30)),
            'max_deliver' => (int) env('NATS_CONSUMER_PAYMENTS_MAX_DELIVER', env('NATS_CONSUMER_MAX_DELIVER', 5)),
        ],
        'inventory' => [
            'durable_name' => env('NATS_CONSUMER_INVENTORY', 'svc_inventory_payments_completed'),
            'filter_subject' => 'payments.completed',
            'ack_wait_seconds' => (int) env('NATS_CONSUMER_INVENTORY_ACK_WAIT', env('NATS_CONSUMER_ACK_WAIT', 30)),
            'max_deliver' => (int) env('NATS_CONSUMER_INVENTORY_MAX_DELIVER', env('NATS_CONSUMER_MAX_DELIVER', 5)),
        ],
        'notifications' => [
            'durable_name' => env('NATS_CONSUMER_NOTIFICATIONS', 'svc_notifications_payments'),
            'filter_subjects' => ['payments.completed', 'payments.failed'],
            'ack_wait_seconds' => (int) env('NATS_CONSUMER_NOTIFICATIONS_ACK_WAIT', env('NATS_CONSUMER_ACK_WAIT', 30)),
            'max_deliver' => (int) env('NATS_CONSUMER_NOTIFICATIONS_MAX_DELIVER', env('NATS_CONSUMER_MAX_DELIVER', 5)),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | NAK delay (seconds) passed to basis Msg::nack($delay) for transient paths
    |--------------------------------------------------------------------------
    */
    'nack_delay_seconds' => (float) env('NATS_NACK_DELAY_SECONDS', 2.0),

    /*
    |--------------------------------------------------------------------------
    | Payment simulation (transient → exception → NAK; terminal → payments.failed)
    |--------------------------------------------------------------------------
    */
    'payment_transient_fail_percent' => (int) env('NATS_PAYMENT_TRANSIENT_FAIL_PERCENT', 35),
    'payment_terminal_fail_percent' => (int) env('NATS_PAYMENT_TERMINAL_FAIL_PERCENT', 10),

    'pull_batch' => (int) env('NATS_ORDER_PULL_BATCH', 8),
    'pull_expires_seconds' => (float) env('NATS_ORDER_PULL_EXPIRES', 0.75),

    'event_version' => env('NATS_ORDER_EVENT_VERSION', 'v1'),

    'idempotency_ttl_seconds' => (int) env('NATS_ORDER_IDEMPOTENCY_TTL', 86400),
];
