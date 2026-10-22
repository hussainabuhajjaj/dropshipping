<?php

return [
    'enabled' => env('SEARCH_DRIVER', 'mysql') === 'typesense',
    'collection' => env('TYPESENSE_COLLECTION', 'products'),
    'api_key' => env('TYPESENSE_API_KEY'),
    'nodes' => [
        [
            'host' => env('TYPESENSE_HOST', 'localhost'),
            'port' => env('TYPESENSE_PORT', 8108),
            'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
        ],
    ],
    'nearest_node' => [
        'host' => env('TYPESENSE_NEAREST_HOST', env('TYPESENSE_HOST', 'localhost')),
        'port' => env('TYPESENSE_NEAREST_PORT', env('TYPESENSE_PORT', 8108)),
        'protocol' => env('TYPESENSE_NEAREST_PROTOCOL', env('TYPESENSE_PROTOCOL', 'http')),
    ],
    'connection_timeout_seconds' => env('TYPESENSE_TIMEOUT', 2),
];
