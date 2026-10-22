<?php

return [
    'default' => env('PAYMENT_DEFAULT', 'paystack'),

    'korapay_enabled' => env('KORAPAY_ENABLED', true),
    'korapay_visible_on_checkout' => env('KORAPAY_VISIBLE_ON_CHECKOUT', false),

    'paystack_enabled' => env('PAYSTACK_ENABLED', true),
    'paystack_mobile_money_enabled' => env('PAYSTACK_MOBILE_MONEY_ENABLED', true),
    'paystack_mobile_money' => [
        'GHS' => ['mtn', 'atl', 'vod'],
        'KES' => ['mpesa', 'mpesa_offline'],
        'XOF' => ['orange', 'wave', 'mtn'],
    ],
];
