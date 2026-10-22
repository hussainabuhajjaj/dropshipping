<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Language
    |--------------------------------------------------------------------------
    |
    | This option determines the default language for the application.
    | You can change this value in your .env file.
    |
    */
    'default' => env('APP_LOCALE', 'fr'),

    /*
    |--------------------------------------------------------------------------
    | Supported Languages
    |--------------------------------------------------------------------------
    |
    | This array contains all the languages that are supported by the
    | application. The key is the language code and the value is the
    | display name for the language.
    |
    */
    'supported' => [
        'en' => 'English',
        'fr' => 'Français',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Language
    |--------------------------------------------------------------------------
    |
    | The fallback language determines the language to use when the current
    | language is not available. You may change the value to correspond
    | to any of the language files that are available.
    |
    */
    'fallback' => 'fr',

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | This option determines how long language translations should be
    | cached in seconds.
    |
    */
    'cache_ttl' => env('LOCALIZATION_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Auto Detect Language
    |--------------------------------------------------------------------------
    |
    | Enable this option to automatically detect the user's preferred
    | language from their browser settings.
    |
    */
    'auto_detect' => env('LOCALIZATION_AUTO_DETECT', false),
];
