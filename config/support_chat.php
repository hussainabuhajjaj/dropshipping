<?php

declare(strict_types=1);

return [
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
