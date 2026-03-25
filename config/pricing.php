<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pricing Configuration
    |--------------------------------------------------------------------------
    */

    'min_margin_percent' => env('PRICING_MIN_MARGIN_PERCENT', 50),
    'default_margin' => env('PRICING_DEFAULT_MARGIN', 0.35),
    'minimum_profit_margin' => env('PRICING_MINIMUM_PROFIT_MARGIN', 20),
    'shipping_buffer_percent' => env('PRICING_SHIPPING_BUFFER_PERCENT', 10),
    'default_shipping_per_kg' => env('PRICING_DEFAULT_SHIPPING_PER_KG', 14),
    'use_new_engine' => env('PRICING_USE_NEW_ENGINE', true),
    'new_engine_rollout' => [
        'percentage' => env('PRICING_NEW_ENGINE_PERCENTAGE', 100),
        'product_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('PRICING_NEW_ENGINE_PRODUCT_IDS', ''))))),
        'cj_pids' => array_values(array_filter(array_map('trim', explode(',', (string) env('PRICING_NEW_ENGINE_CJ_PIDS', ''))))),
        'category_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('PRICING_NEW_ENGINE_CATEGORY_IDS', ''))))),
    ],
    'weight_margins' => [
        ['max' => 0.5, 'margin' => 0.65],
        ['max' => 1.0, 'margin' => 0.55],
        ['max' => 2.0, 'margin' => 0.45],
        ['max' => null, 'margin' => 0.30],
    ],
    'max_discount_percent' => env('PRICING_MAX_DISCOUNT_PERCENT', 30),
    'max_promotion_discount' => env('PRICING_MAX_PROMOTION_DISCOUNT', 30),

    'category_multipliers' => [
        1 => env('PRICING_ELECTRONICS_MULTIPLIER', 1.2), // Electronics
        2 => env('PRICING_CLOTHING_MULTIPLIER', 1.1), // Clothing
        3 => env('PRICING_HOME_GARDEN_MULTIPLIER', 1.15), // Home & Garden
        4 => env('PRICING_BEAUTY_MULTIPLIER', 1.25), // Beauty
        5 => env('PRICING_SPORTS_MULTIPLIER', 1.1), // Sports
        'default' => env('PRICING_DEFAULT_MULTIPLIER', 1.0),
    ],

    'currency' => [
        'default' => env('PRICING_DEFAULT_CURRENCY', 'XOF'),
        'exchange_rate_cache_ttl' => env('PRICING_EXCHANGE_RATE_CACHE_TTL', 3600),
        'xof_buffer_percent' => env('PRICING_XOF_BUFFER_PERCENT', 5),
    ],

    'shipping_costs' => [
        'CI' => env('PRICING_SHIPPING_CI', 12.50),
        'US' => env('PRICING_SHIPPING_US', 8.00),
        'FR' => env('PRICING_SHIPPING_FR', 15.00),
        'UK' => env('PRICING_SHIPPING_UK', 12.00),
        'default' => env('PRICING_SHIPPING_DEFAULT', 10.00),
    ],

    'fees' => [
        'payment_gateway' => env('PRICING_PAYMENT_GATEWAY_FEE', 3.5),
        'platform' => env('PRICING_PLATFORM_FEE', 5.0),
    ],

    'queues' => [
        'validation' => env('PRICING_VALIDATION_QUEUE', 'pricing'),
        'bulk_margin' => env('PRICING_BULK_MARGIN_QUEUE', 'pricing'),
    ],

    'alerts' => [
        'low_margin_threshold' => env('PRICING_LOW_MARGIN_THRESHOLD', 20),
        'high_currency_buffer_threshold' => env('PRICING_HIGH_CURRENCY_BUFFER_THRESHOLD', 10),
        'rate_change_threshold' => env('PRICING_RATE_CHANGE_THRESHOLD', 5),
    ],
];
