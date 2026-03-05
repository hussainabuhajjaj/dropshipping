<?php

return [
    // Default base currency for pricing data
    'base' => env('CURRENCY_BASE', 'USD'),

    // Supported currencies for user selection
    'supported' => [
        'USD', // US Dollar
        'XOF', // West African CFA Franc
    ],

    // Direct FX rates, e.g. USD_XOF for USD -> West African CFA franc
    'rates' => [
        'USD_XOF' => env('FX_USD_XOF', 600), // Default rate: 1 USD = 600 XOF
        'XOF_USD' => env('FX_XOF_USD', 0.00167), // Default rate: 1 XOF = 0.00167 USD
    ],

    // Decimal places per currency (XOF typically has no minor unit)
    'decimals' => [
        'USD' => 2,
        'XOF' => 0,
    ],

    // Cache TTL for currency rates
    'cache_ttl' => env('CURRENCY_CACHE_TTL', 3600),

    // Rate provider service
    'rate_provider' => env('CURRENCY_RATE_PROVIDER', 'fixed'),

    // Aliases for common mis-typed codes
    'aliases' => [
        'XFC' => 'XAF',
        'XFA' => 'XAF',
    ],
];
