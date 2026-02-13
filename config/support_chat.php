<?php

declare(strict_types=1);

return [
    'queue' => env('SUPPORT_CHAT_QUEUE', 'support'),
    'attachments' => [
        'disk' => env('SUPPORT_CHAT_ATTACHMENT_DISK', 'public'),
        'max_kb' => (int) env('SUPPORT_CHAT_ATTACHMENT_MAX_KB', 10240),
        'allowed_mimes' => array_values(array_filter(array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            explode(',', (string) env('SUPPORT_CHAT_ATTACHMENT_ALLOWED_MIMES', 'image/jpeg,image/png,image/webp,application/pdf,text/plain'))
        ))),
        'image_max_width' => (int) env('SUPPORT_CHAT_ATTACHMENT_IMAGE_MAX_WIDTH', 1600),
        'image_quality' => (int) env('SUPPORT_CHAT_ATTACHMENT_IMAGE_QUALITY', 82),
        'image_convert_to_webp' => (bool) env('SUPPORT_CHAT_ATTACHMENT_IMAGE_CONVERT_TO_WEBP', true),
    ],
    'realtime' => [
        'enabled' => (bool) env('SUPPORT_CHAT_REALTIME_ENABLED', true),
    ],
    'escalation' => [
        'enabled' => (bool) env('SUPPORT_CHAT_ESCALATION_ENABLED', true),
        'sla_minutes' => (int) env('SUPPORT_CHAT_SLA_MINUTES', 15),
        'repeat_minutes' => (int) env('SUPPORT_CHAT_ESCALATION_REPEAT_MINUTES', 60),
        'max_per_run' => (int) env('SUPPORT_CHAT_ESCALATION_MAX_PER_RUN', 200),
    ],
    'digest' => [
        'enabled' => (bool) env('SUPPORT_CHAT_DIGEST_ENABLED', true),
        'send_empty' => (bool) env('SUPPORT_CHAT_DIGEST_SEND_EMPTY', false),
        'max_rows' => (int) env('SUPPORT_CHAT_DIGEST_MAX_ROWS', 10),
    ],
];
