<?php

return [
    // Default base currency for pricing data
    'base' => env('CURRENCY_BASE', 'USD'),

    // Supported currencies for user selection
    'supported' => [
        'USD', // US Dollar
        'CNY', // Chinese Yuan Renminbi
        'XOF', // West African CFA Franc
    ],

    // Direct FX rates, e.g. USD_XOF for USD -> West African CFA franc
    'rates' => [
        'USD_XOF' => env('FX_USD_XOF', 600), // Default rate: 1 USD = 600 XOF
        'XOF_USD' => env('FX_XOF_USD', 0.00167), // Default rate: 1 XOF = 0.00167 USD
        'CNY_USD' => env('FX_CNY_USD', 0.1488), // Default rate: 1 CNY = 0.1488 USD
        'USD_CNY' => env('FX_USD_CNY', 6.72), // Default rate: 1 USD = 6.72 CNY
        'CNY_XOF' => env('FX_CNY_XOF', 89.28), // Based on the default USD_XOF rate
        'XOF_CNY' => env('FX_XOF_CNY', 0.0112),
    ],

    // Decimal places per currency (XOF typically has no minor unit)
    'decimals' => [
        'USD' => 2,
        'CNY' => 2,
        'XOF' => 0,
    ],

    // Cache TTL for currency rates
    'cache_ttl' => env('CURRENCY_CACHE_TTL', 3600),

    // Rate provider service
    'rate_provider' => env('CURRENCY_RATE_PROVIDER', 'fixed'),

    // Aliases for common mis-typed codes
    'aliases' => [
        'XAF' => 'XOF',
        'XFC' => 'XOF',
        'XFA' => 'XOF',
        'RMB' => 'CNY',
        'CNH' => 'CNY',
    ],
];
