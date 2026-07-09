<?php

return [
    'android' => [
        'package_name' => env('MOBILE_ANDROID_PACKAGE_NAME', 'com.simbazu.mobile'),
        'version_name' => env('APK_VERSION_NAME', '1.0.0'),
        'version_code' => env('APK_VERSION_CODE', 1),
        'filename' => env('APK_FILENAME', 'simbazu-v1.0.0.apk'),
        'size_mb' => env('APK_SIZE_MB', 25),
        'min_sdk' => env('APK_MIN_SDK', 24),
        'target_sdk' => env('APK_TARGET_SDK', 34),
        'updated_at' => env('APK_UPDATED_AT', '2026-07-10'),
        'changelog' => env('APK_CHANGELOG', 'Initial release.'),
        'download_url' => env('APK_DOWNLOAD_URL', '/download/apk'),
    ],
    'ios' => [
        'bundle_id' => env('MOBILE_IOS_BUNDLE_ID', 'net.simbazu.mobile'),
        'appstore_url' => env('IOS_APPSTORE_URL', ''),
    ],
    'features' => [
        'Fast checkout with saved addresses',
        'Real-time order tracking',
        'Push notifications for order updates',
        'Secure payments via Paystack',
        'Multi-currency support (USD, XOF)',
        'Wishlist & recently viewed',
        '24/7 customer support',
        'Exclusive mobile-only deals',
    ],
];
