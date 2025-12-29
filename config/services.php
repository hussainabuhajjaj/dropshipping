<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'payments' => [
        'webhook_secret' => env('PAYMENTS_WEBHOOK_SECRET'),
    ],

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'shippo' => [
        'api_token' => env('SHIPPO_API_TOKEN'),
        'base_url' => env('SHIPPO_BASE_URL', 'https://api.goshippo.com'),
    ],

    'returns' => [
        'address_line1' => env('RETURNS_ADDRESS_LINE1', '123 Return Center'),
        'address_line2' => env('RETURNS_ADDRESS_LINE2'),
        'city' => env('RETURNS_CITY', 'City'),
        'state' => env('RETURNS_STATE', 'State'),
        'postal_code' => env('RETURNS_POSTAL_CODE', '00000'),
        'country' => env('RETURNS_COUNTRY', 'US'),
        'phone' => env('RETURNS_PHONE', '+1234567890'),
    ],

    'tracking' => [
        'webhook_secret' => env('TRACKING_WEBHOOK_SECRET'),
    ],

    'cj' => [
        'app_id' => env('CJ_APP_ID'),
        'api_secret' => env('CJ_API_SECRET'),
        'api_key' => env('CJ_API_KEY'),
        'base_url' => env('CJ_BASE_URL', 'https://developers.cjdropshipping.com/api2.0'),
        'timeout' => env('CJ_TIMEOUT', 10),
        'webhook_secret' => env('CJ_WEBHOOK_SECRET'),
        'platform_token' => env('CJ_PLATFORM_TOKEN'),
        'alerts_email' => env('CJ_ALERTS_EMAIL'),
        // Optional: default ship-to country for CJ imports (e.g., 'US', 'GB').
        // When null or empty, imports will not be filtered by ship-to.
        'ship_to_default' => env('CJ_SHIP_TO_DEFAULT'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI'),
    ],

    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'meta'),
        'meta' => [
            'token' => env('WHATSAPP_META_TOKEN'),
            'phone_number_id' => env('WHATSAPP_META_PHONE_NUMBER_ID'),
            'api_version' => env('WHATSAPP_META_API_VERSION', 'v19.0'),
            'base_url' => env('WHATSAPP_META_BASE_URL', 'https://graph.facebook.com'),
        ],
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_WHATSAPP_FROM'),
        ],
        'vonage' => [
            'jwt' => env('VONAGE_JWT'),
            'from' => env('VONAGE_WHATSAPP_FROM'),
            'endpoint' => env('VONAGE_WHATSAPP_ENDPOINT', 'https://api.nexmo.com/v1/messages'),
        ],
    ],
    'deepseek' => [
        'key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
        'timeout' => env('DEEPSEEK_TIMEOUT', 20),
    ],

    'libre_translate' => [
        'base_url' => env('LIBRE_TRANSLATE_BASE_URL', 'https://libretranslate.de'),
        'key' => env('LIBRE_TRANSLATE_API_KEY'),
        'timeout' => env('LIBRE_TRANSLATE_TIMEOUT', 10),
    ],

    'translation_provider' => env('TRANSLATION_PROVIDER', 'libre_translate'),
    'translation_locales' => explode(',', (string) env('TRANSLATION_LOCALES', 'en,fr')),
    'translation_source_locale' => env('TRANSLATION_SOURCE_LOCALE', 'en'),

];
