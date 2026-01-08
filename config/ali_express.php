<?php

return [
    'client_id' => env('ALIEXPRESS_CLIENT_ID'),  // Changed from ALI_EXPRESS_CLIENT_ID
    'client_secret' => env('ALIEXPRESS_CLIENT_SECRET'),  // Changed from ALI_EXPRESS_CLIENT_SECRET
    'api_base' => env('ALIEXPRESS_API_BASE', 'https://openapi.aliexpress.com/gateway.do'),
    'redirect_uri' => env('ALIEXPRESS_REDIRECT_URI'),
    // Add more config as needed
];
