<?php

return [
    // Default base currency for pricing data
    'base' => env('CURRENCY_BASE', 'USD'),

    // Direct FX rates, e.g. USD_XAF for USD -> Central African CFA franc
    'rates' => [
        'USD_XAF' => env('FX_USD_XAF'),
        'USD_XOF' => env('FX_USD_XOF', env('FX_USD_XAF')),
    ],

    // Decimal places per currency (XAF typically has no minor unit)
    'decimals' => [
        'USD' => 2,
        'XAF' => 0,
        'XOF' => 0,
    ],

    // Aliases for common mis-typed codes
    'aliases' => [
        'XFC' => 'XAF',
        'XFA' => 'XAF',
    ],
];
