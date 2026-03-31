<?php

declare(strict_types=1);

/**
 * Order pipeline: JetStream stream, subjects, durable consumers, retry/DLQ tuning.
 *
 * @see README.md — distributed order processing PoC (laravel-nats + basis-company/nats).
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
    | Durable pull consumers (explicit ack; filter per service)
    |--------------------------------------------------------------------------
    */
    'consumers' => [
        'orders' => [
            'durable_name' => env('NATS_CONSUMER_ORDERS', 'svc_orders_order_created'),
            'filter_subject' => 'orders.created',
            'ack_wait_seconds' => (int) env('NATS_CONSUMER_ACK_WAIT', 120),
            'max_deliver' => (int) env('NATS_CONSUMER_MAX_DELIVER', 10),
        ],
        'payments' => [
            'durable_name' => env('NATS_CONSUMER_PAYMENTS', 'svc_payments_order_created'),
            'filter_subject' => 'orders.created',
            'ack_wait_seconds' => (int) env('NATS_CONSUMER_ACK_WAIT', 120),
            'max_deliver' => (int) env('NATS_CONSUMER_MAX_DELIVER', 10),
        ],
        'inventory' => [
            'durable_name' => env('NATS_CONSUMER_INVENTORY', 'svc_inventory_payments_completed'),
            'filter_subject' => 'payments.completed',
            'ack_wait_seconds' => (int) env('NATS_CONSUMER_ACK_WAIT', 120),
            'max_deliver' => (int) env('NATS_CONSUMER_MAX_DELIVER', 10),
        ],
        'notifications' => [
            'durable_name' => env('NATS_CONSUMER_NOTIFICATIONS', 'svc_notifications_payments'),
            'filter_subjects' => ['payments.completed', 'payments.failed'],
            'ack_wait_seconds' => (int) env('NATS_CONSUMER_ACK_WAIT', 120),
            'max_deliver' => (int) env('NATS_CONSUMER_MAX_DELIVER', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Application-level retry before DLQ (counted per JetStream message delivery)
    |--------------------------------------------------------------------------
    */
    'max_processing_attempts_before_dlq' => (int) env('NATS_ORDER_MAX_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Payment simulation (transient failure → NACK; terminal → payments.failed)
    |--------------------------------------------------------------------------
    */
    'payment_transient_fail_percent' => (int) env('NATS_PAYMENT_TRANSIENT_FAIL_PERCENT', 35),
    'payment_terminal_fail_percent' => (int) env('NATS_PAYMENT_TERMINAL_FAIL_PERCENT', 10),

    'pull_batch' => (int) env('NATS_ORDER_PULL_BATCH', 8),
    'pull_expires_seconds' => (float) env('NATS_ORDER_PULL_EXPIRES', 0.75),

    'event_version' => env('NATS_ORDER_EVENT_VERSION', 'v1'),

    'idempotency_ttl_seconds' => (int) env('NATS_ORDER_IDEMPOTENCY_TTL', 86400),
];
