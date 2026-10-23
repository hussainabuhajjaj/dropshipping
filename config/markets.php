<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront Markets (Phase 1 — minimal)
    |--------------------------------------------------------------------------
    |
    | A market is derived from the shipping destination country only.
    | There is NO IP geolocation and no automatic detection. The default
    | country preserves 100% of existing CI behavior for every visitor who
    | has not explicitly selected another market.
    |
    */

    'default_country' => env('MARKET_DEFAULT_COUNTRY', 'CI'),

    'markets' => [
        'ci' => [
            'label' => 'Côte d’Ivoire',
            'countries' => ['CI'],
            // CI prices come straight from products.selling_price / variant.price.
            // Those stored prices already embed the CI external_shipping component.
            'prices_from_store' => true,
        ],
        'uae' => [
            'label' => 'United Arab Emirates',
            'countries' => ['AE'],
            // UAE prices are computed at runtime from persisted pricing inputs,
            // WITHOUT the CI external_shipping component. Checkout freight is a
            // separate CN -> AE CJ quote (see CJFreightService).
            'prices_from_store' => false,
        ],
    ],
];
