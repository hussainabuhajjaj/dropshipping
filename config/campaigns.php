<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Global Campaign Feature Flags
    |--------------------------------------------------------------------------
    | Master switches. When a flag is false the corresponding campaign
    | subsystem is fully inert: nothing renders, no listeners register
    | participation, no rewards are issued.
    */

    'lucky_draw' => [
        'enabled' => (bool) env('CAMPAIGN_LUCKY_DRAW_ENABLED', false),

        /*
        | Default configuration values applied when a lucky-draw campaign
        | is created without explicit values. Individual campaigns override
        | these via the `lucky_draw_config` JSON column.
        */
        'defaults' => [
            'min_order_amount' => 30000,
            'currency' => 'XOF',
            'max_participants' => 50,
            'grand_prize' => 'iPhone 17 Pro Max',
            'runner_up_count' => 10,
            'gift_card_amount' => 20,
            'gift_card_currency' => 'USD',
            'guaranteed_reward_type' => 'coupon_code',
            'guaranteed_reward_value' => 10,
            'show_remaining_spots' => true,
            'countdown_enabled' => true,
        ],
    ],

];
