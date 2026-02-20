<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Production Pricing Configuration
    |--------------------------------------------------------------------------
    |
    | Production-optimized pricing settings with performance and safety features
    | enabled. These settings are optimized for high-volume dropshipping operations.
    |
    */

    'min_margin_percent' => env('PRICING_MIN_MARGIN_PERCENT', 45.0),
    'max_discount_percent' => env('PRICING_MAX_DISCOUNT_PERCENT', 30.0),
    'minimum_profit_margin' => env('PRICING_MINIMUM_PROFIT_MARGIN', 15.0),

    /*
    |--------------------------------------------------------------------------
    | Currency Settings
    |--------------------------------------------------------------------------
    */
    'currency' => [
        'default' => env('PRICING_DEFAULT_CURRENCY', 'XOF'),
        'exchange_rate_cache_ttl' => env('PRICING_EXCHANGE_RATE_CACHE_TTL', 3600), // 1 hour
        'xof_buffer_percent' => env('PRICING_XOF_BUFFER_PERCENT', 5.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fee Configuration
    |--------------------------------------------------------------------------
    */
    'fees' => [
        'platform' => env('PRICING_PLATFORM_FEE', 5.0),
        'payment_gateway' => env('PRICING_PAYMENT_GATEWAY_FEE', 3.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Category Multipliers
    |--------------------------------------------------------------------------
    |
    | Category-specific pricing multipliers for different product categories.
    | These are cached in production for better performance.
    |
    */
    'category_multipliers' => [
        1 => env('PRICING_CATEGORY_MULTIPLIER_1', 1.2),
        2 => env('PRICING_CATEGORY_MULTIPLIER_2', 1.1),
        3 => env('PRICING_CATEGORY_MULTIPLIER_3', 1.3),
        // Add more categories as needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Production performance optimizations for pricing operations.
    |
    */
    'performance' => [
        'enable_cache' => env('PRICING_ENABLE_CACHE', true),
        'cache_ttl' => env('PRICING_CACHE_TTL', 3600), // 1 hour
        'enable_query_optimization' => env('PRICING_ENABLE_QUERY_OPTIMIZATION', true),
        'bulk_chunk_size' => env('PRICING_BULK_CHUNK_SIZE', 100),
        'max_concurrent_updates' => env('PRICING_MAX_CONCURRENT_UPDATES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Logging
    |--------------------------------------------------------------------------
    |
    | Production monitoring and logging configuration.
    |
    */
    'monitoring' => [
        'enable_operation_logs' => env('PRICING_ENABLE_OPERATION_LOGS', true),
        'log_slow_operations' => env('PRICING_LOG_SLOW_OPERATIONS', true),
        'slow_operation_threshold_ms' => env('PRICING_SLOW_OPERATION_THRESHOLD_MS', 1000),
        'enable_metrics' => env('PRICING_ENABLE_METRICS', true),
        'alert_on_errors' => env('PRICING_ALERT_ON_ERRORS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety and Validation
    |--------------------------------------------------------------------------
    |
    | Production safety settings to prevent pricing errors.
    |
    */
    'safety' => [
        'enable_price_validation' => env('PRICING_ENABLE_PRICE_VALIDATION', true),
        'max_price_change_percent' => env('PRICING_MAX_PRICE_CHANGE_PERCENT', 50.0),
        'require_approval_for_large_changes' => env('PRICING_REQUIRE_APPROVAL_FOR_LARGE_CHANGES', true),
        'enable_rollback_protection' => env('PRICING_ENABLE_ROLLBACK_PROTECTION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Protection
    |--------------------------------------------------------------------------
    |
    | Settings to prevent API rate limiting during pricing operations.
    |
    */
    'rate_limiting' => [
        'enable_protection' => env('PRICING_ENABLE_RATE_LIMIT_PROTECTION', true),
        'max_operations_per_second' => env('PRICING_MAX_OPERATIONS_PER_SECOND', 5),
        'enable_backoff' => env('PRICING_ENABLE_BACKOFF', true),
        'backoff_multiplier' => env('PRICING_BACKOFF_MULTIPLIER', 2.0),
        'max_backoff_seconds' => env('PRICING_MAX_BACKOFF_SECONDS', 60),
    ],
];
