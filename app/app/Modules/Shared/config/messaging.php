<?php

return [
    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST', 'rabbitmq'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'ecommerce'),
        'password' => env('RABBITMQ_PASSWORD', 'secret'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'connect_timeout' => 3.0,
        'confirm_timeout' => 5.0,
        'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 0),
    ],

    'orders_exchange' => 'orders',
    'order_created_key' => 'order.created',

    'order_confirmation_queue' => 'notifications.order-confirmation',

    // Failed messages wait here with a per-message expiration, then return to the main queue.
    'retry_exchange' => 'orders.retry',
    'retry_queue' => 'notifications.order-confirmation.delay',
    'retry_key' => 'order.created.delay',
    'retry_ttl_ms' => (int) env('ORDER_EMAIL_RETRY_TTL_MS', 10000),

    // Messages that exhausted max_attempts are parked here, never dropped.
    'dlq_exchange' => 'orders.dlq',
    'dlq_queue' => 'notifications.order-confirmation.dlq',
    'max_attempts' => (int) env('ORDER_EMAIL_MAX_ATTEMPTS', 5),
    // DEMO ONLY: force every send to fail, to demonstrate retries and the DLQ.
    'fail_sending' => (bool) env('ORDER_EMAIL_FAIL_SENDING', false),

    'outbox_poll_ms' => (int) env('OUTBOX_POLL_MS', 500),
];
