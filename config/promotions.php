<?php

declare(strict_types=1);

return [
    'display' => [
        'enabled' => true,
    ],
    'display_limits' => [
        'home' => 5,
        'category' => 3,
        'product' => 2,
        'cart' => 3,
        'checkout' => 3,
    ],
    'caps' => [
        'first_order_max_discount' => 10.00,
        'high_value_max_discount' => 15.00,
    ],
    'thresholds' => [
        'high_value_min_order' => 50.00,
    ],
    'percentages' => [
        'first_order_discount' => 0.10,
        'high_value_discount' => 0.05,
    ],
    'labels' => [
        'first_order' => 'First order 10% off',
        'high_value' => '5% off orders over $50',
    ],
];
