<?php

return [
    'client_id' => env('ALIEXPRESS_CLIENT_ID'),
    'client_secret' => env('ALIEXPRESS_CLIENT_SECRET'),
    'redirect_uri' => env('ALIEXPRESS_REDIRECT_URI'),
    'api_base' => env('ALIEXPRESS_API_BASE', 'https://openapi.aliexpress.com/gateway.do'),
    // Add more config as needed
];
