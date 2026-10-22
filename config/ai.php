<?php

return [
    'provider' => env('AI_PROVIDER', 'deepseek'),

    'moderation' => [
        // simple blacklist of words; matching is case-insensitive
        'blacklist' => [
            // Add words as needed, e.g. 'bannedword'
        ],
        // Whether to attempt provider-based moderation using DeepSeek
        'use_deepseek' => env('AI_MODERATION_USE_DEEPSEEK', false),
    ],
];
