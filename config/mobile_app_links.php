<?php

declare(strict_types=1);

return [
    'ios' => [
        'team_id' => env('MOBILE_APPLE_TEAM_ID'),
        'bundle_id' => env('MOBILE_IOS_BUNDLE_ID', 'net.simbazu.mobile'),
        'paths' => [
            '/',
            '/products/*',
        ],
    ],
    'android' => [
        'package_name' => env('MOBILE_ANDROID_PACKAGE_NAME', 'com.simbazu.mobile'),
        'sha256_cert_fingerprints' => array_values(array_filter(array_map(
            static fn (?string $value): string => trim((string) $value),
            explode(',', (string) env('MOBILE_ANDROID_SHA256_CERT_FINGERPRINTS', ''))
        ))),
    ],
];
