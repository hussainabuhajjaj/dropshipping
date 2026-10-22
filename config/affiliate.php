<?php

return [
    'cookie_lifetime_days' => env('AFFILIATE_COOKIE_LIFETIME_DAYS', 30),
    'default_commission_rate' => env('AFFILIATE_DEFAULT_COMMISSION_RATE', 0.10),
    'minimum_withdrawal' => env('AFFILIATE_MINIMUM_WITHDRAWAL', 50.00),
    'auto_approve_event' => env('AFFILIATE_AUTO_APPROVE', false),
    'auto_approve_days' => env('AFFILIATE_AUTO_APPROVE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Referral Discount for Customers
    |--------------------------------------------------------------------------
    | When a customer visits via ?ref=CODE, an affiliate-specific coupon is
    | auto-generated and applied to give the referred customer a discount.
    */
    'referral_discount_percent' => env('AFFILIATE_REFERRAL_DISCOUNT_PERCENT', 10),
    'referral_coupon_max_uses' => env('AFFILIATE_REFERRAL_COUPON_MAX_USES', 0),
    'referral_coupon_valid_days' => env('AFFILIATE_REFERRAL_COUPON_VALID_DAYS', 30),
];
