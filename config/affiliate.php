<?php

return [
    'cookie_lifetime_days' => env('AFFILIATE_COOKIE_LIFETIME_DAYS', 30),
    'default_commission_rate' => env('AFFILIATE_DEFAULT_COMMISSION_RATE', 0.10),
    'minimum_withdrawal' => env('AFFILIATE_MINIMUM_WITHDRAWAL', 50.00),
    'auto_approve_event' => env('AFFILIATE_AUTO_APPROVE', false),
    'auto_approve_days' => env('AFFILIATE_AUTO_APPROVE_DAYS', 7),
];
