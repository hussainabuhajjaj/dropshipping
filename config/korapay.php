<?php
// Legacy Korapay config (kept for backward compatibility).
// Do NOT hardcode secrets here. Use env vars and prefer `config/services.php` (`services.korapay.*`) in new code.
return [
    'secret_key' => env('KORAPAY_SECRET_KEY'),
    'public_key' => env('KORAPAY_PUBLIC_KEY'),
    'webhook_secret' => env('KORAPAY_WEBHOOK_SECRET'),
    'baseUrl' => env('KORAPAY_BASE_URL', 'https://api.korapay.com/'),
];
// return [

//     'secret_key' => 'sk_test_yGrnyyXkFimATgWnFfzS5r2nCWEgkN9QKwdUmeR9',
//     'public_key' => 'pk_test_9LExUGzUwZ2JCVHpEYpabRDFsXGsEs2EVP3aGump',
//     'baseUrl' => 'https://api.korapay.com/',
// ];
