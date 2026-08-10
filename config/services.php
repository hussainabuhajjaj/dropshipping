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
        'test_mode' => env('PAYSTACK_TEST_MODE', false),
    ],

    'korapay' => [
        'secret_key' => env('KORAPAY_SECRET_KEY'),
        'public_key' => env('KORAPAY_PUBLIC_KEY'),
        'webhook_secret' => env('KORAPAY_WEBHOOK_SECRET'),
        'base_url' => env('KORAPAY_BASE_URL'),
        'initialize_endpoint' => env('KORAPAY_INITIALIZE_ENDPOINT'),
        'verify_endpoint' => env('KORAPAY_VERIFY_ENDPOINT'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'verify_token' => env('META_VERIFY_TOKEN'),
        'page_access_token' => env('META_PAGE_ACCESS_TOKEN'),
        'instagram_business_account_id' => env('META_INSTAGRAM_BUSINESS_ACCOUNT_ID'),
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
        'warehouse_list_endpoint' => env('CJ_WAREHOUSE_LIST_ENDPOINT', '/v1/product/globalWarehouse/list'),
        'timeout' => env('CJ_TIMEOUT', 10),
        // CJ frequently enforces very low QPS limits (sometimes 1 request/second). We throttle
        // some admin bulk actions (sourcing/product lookups) to avoid code 1600200.
        'qps_delay_ms' => (int) env('CJ_QPS_DELAY_MS', 1100),
        'webhook_secret' => env('CJ_WEBHOOK_SECRET'),
        'platform_token' => env('CJ_PLATFORM_TOKEN'),
        'alerts_email' => env('CJ_ALERTS_EMAIL'),
        // Optional: default ship-to country for CJ imports (e.g., 'US', 'GB').
        // When null or empty, imports will not be filtered by ship-to.
        'ship_to_default' => env('CJ_SHIP_TO_DEFAULT'),

        // Import Pipeline Settings
        'import_margin' => env('CJ_IMPORT_MARGIN', 60),
        'import_enrich' => env('CJ_IMPORT_ENRICH', true),
        'import_enrich_sleep_ms' => env('CJ_IMPORT_ENRICH_SLEEP_MS', 200),
        'import_auto_activate' => env('CJ_IMPORT_AUTO_ACTIVATE', true),
        'import_chunk_size' => env('CJ_IMPORT_CHUNK_SIZE', 25),
        'stock_queue' => env('CJ_STOCK_QUEUE', 'cj-sync'),
        'stock_percentage' => env('CJ_STOCK_PERCENTAGE', 75.0), // Use 75% instead of 50%

        // CRITICAL FIX: Price validation and corruption prevention settings
        'max_markup_multiplier' => env('CJ_MAX_MARKUP_MULTIPLIER', 15.0), // Maximum allowed markup (15x = corruption)
        'reasonable_markup_multiplier' => env('CJ_REASONABLE_MARKUP_MULTIPLIER', 10.0), // Maximum reasonable markup (10x)
        'default_fulfillment_provider_id' => env('CJ_DEFAULT_FULFILLMENT_PROVIDER_ID', 1),

        /*
        |--------------------------------------------------------------------------
        | Payment Monitoring Configuration
        |--------------------------------------------------------------------------
        */
        'payment_monitoring' => [
            'alerts_email' => env('PAYMENT_ALERTS_EMAIL', 'admin@example.com'),
            'failed_webhook_threshold_minutes' => env('FAILED_WEBHOOK_THRESHOLD_MINUTES', 60),
            'redirect_timeout_minutes' => env('REDIRECT_TIMEOUT_MINUTES', 30),
            'slow_completion_threshold_minutes' => env('SLOW_COMPLETION_THRESHOLD_MINUTES', 30),
            'data_capture_rate_threshold' => env('DATA_CAPTURE_RATE_THRESHOLD', 80),
        ],
    ],
    'watermark' => [
        'logo_path' => 'public/images/category-default.png',  // Path to your logo in storage
        'opacity' => 50,                   // 0-100, 50 is semi-transparent
        'position' => 'top-right',      // top-left, top-right, bottom-left, bottom-right, center
        'margin' => 20,                    // Pixels from edges
    ],
    'queue_reporting' => [
        'enabled' => env('QUEUE_REPORTING_ENABLED', false),
        'emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('QUEUE_REPORTING_EMAILS', (string) env('CJ_ALERTS_EMAIL', '')))
        ))),
        'interval_minutes' => (int) env('QUEUE_REPORTING_INTERVAL_MINUTES', 10),
        'send_empty' => env('QUEUE_REPORTING_SEND_EMPTY', false),
    ],

    'queue_reporting' => [
        'enabled' => env('QUEUE_REPORTING_ENABLED', false),
        'emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('QUEUE_REPORTING_EMAILS', (string) env('CJ_ALERTS_EMAIL', '')))
        ))),
        'interval_minutes' => (int) env('QUEUE_REPORTING_INTERVAL_MINUTES', 10),
        'send_empty' => env('QUEUE_REPORTING_SEND_EMPTY', false),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim((string) env('APP_URL', ''), '/') . '/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', rtrim((string) env('APP_URL', ''), '/') . '/auth/facebook/callback'),
        'pixel_id' => env('FACEBOOK_PIXEL_ID'),
    ],

    'meta_ads' => [
        'dataset_id' => env('META_CAPI_DATASET_ID'),
        'access_token' => env('META_CAPI_ACCESS_TOKEN'),
        'api_version' => env('META_CAPI_API_VERSION', 'v21.0'),
        'test_event_code' => env('META_CAPI_TEST_EVENT_CODE'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI', rtrim((string) env('APP_URL', ''), '/') . '/auth/apple/callback'),
        'key_id' => env('APPLE_KEY_ID'),
        'team_id' => env('APPLE_TEAM_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
        'passphrase' => env('APPLE_PASSPHRASE'),
        'signer' => env('APPLE_SIGNER'),
        'jwt_issued_time_leeway' => env('APPLE_JWT_ISSUED_TIME_LEEWAY', 'PT3S'),
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
        'connect_timeout' => env('DEEPSEEK_CONNECT_TIMEOUT', 10),
        'timeout' => env('DEEPSEEK_TIMEOUT', 45),
        'retry_times' => env('DEEPSEEK_RETRY_TIMES', 3),
        'retry_delay_ms' => env('DEEPSEEK_RETRY_DELAY_MS', 500),
    ],

    'libre_translate' => [
        'base_url' => env('LIBRE_TRANSLATE_BASE_URL', 'https://libretranslate.de'),
        'key' => env('LIBRE_TRANSLATE_API_KEY'),
        'timeout' => env('LIBRE_TRANSLATE_TIMEOUT', 10),
    ],

    'translation_provider' => env('TRANSLATION_PROVIDER', 'libre_translate'),
    'translation_locales' => explode(',', (string) env('TRANSLATION_LOCALES', 'en,fr')),
    'translation_source_locale' => env('TRANSLATION_SOURCE_LOCALE', 'en'),

    'tiktok' => [
        'pixel_id' => env('TIKTOK_PIXEL_ID'),
    ],

];
