<?php

declare(strict_types=1);

return [

    'enabled' => env('WC_ENABLED', false),

    'base_url' => env('WC_BASE_URL', ''),
    'consumer_key' => env('WC_CONSUMER_KEY', ''),
    'consumer_secret' => env('WC_CONSUMER_SECRET', ''),
    'webhook_secret' => env('WC_WEBHOOK_SECRET', ''),

    'timeout' => (int) env('WC_TIMEOUT', 30),
    'retry_times' => (int) env('WC_RETRY_TIMES', 3),
    'retry_delay_ms' => (int) env('WC_RETRY_DELAY_MS', 500),
    'verify_ssl' => (bool) env('WC_VERIFY_SSL', true),

    'queue' => env('WC_QUEUE', 'woocommerce'),

    'order_status_map' => [
        'pending' => 'pending',
        'paid' => 'processing',
        'processing' => 'processing',
        'fulfilled' => 'completed',
        'shipped' => 'completed',
        'delivered' => 'completed',
        'cancelled' => 'cancelled',
        'refunded' => 'refunded',
    ],

    'webhook_status_map' => [
        'pending' => 'pending',
        'processing' => 'paid',
        'completed' => 'delivered',
        'cancelled' => 'cancelled',
        'refunded' => 'refunded',
        'failed' => 'cancelled',
        'on-hold' => 'processing',
    ],

    'batch_size' => (int) env('WC_BATCH_SIZE', 50),
    'product_chunk_size' => (int) env('WC_PRODUCT_CHUNK_SIZE', 25),

    'webhook_events' => [
        'order.created',
        'order.updated',
        'order.deleted',
        'customer.created',
        'customer.updated',
        'product.created',
        'product.updated',
        'product.deleted',
    ],
];
