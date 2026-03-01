<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Story Types Configuration
    |--------------------------------------------------------------------------
    |
    | Define all available story types and their configurations.
    | Each type can have custom fields, validation rules, and display settings.
    |
    */

    'types' => [
        'offer' => [
            'label' => 'Special Offer',
            'icon' => 'tag',
            'fields' => [
                'discount_percent' => ['type' => 'number', 'label' => 'Discount %', 'required' => false],
                'discount_code' => ['type' => 'string', 'label' => 'Promo Code', 'required' => false],
                'expires_at' => ['type' => 'datetime', 'label' => 'Expires At', 'required' => false],
                'min_purchase' => ['type' => 'number', 'label' => 'Min Purchase Amount', 'required' => false],
                'terms' => ['type' => 'text', 'label' => 'Terms & Conditions', 'required' => false],
            ],
            'cta_required' => true,
        ],

        'promotion' => [
            'label' => 'Promotion Campaign',
            'icon' => 'megaphone',
            'fields' => [
                'campaign_name' => ['type' => 'string', 'label' => 'Campaign Name', 'required' => true],
                'tagline' => ['type' => 'string', 'label' => 'Tagline', 'required' => false],
                'start_date' => ['type' => 'datetime', 'label' => 'Start Date', 'required' => false],
                'end_date' => ['type' => 'datetime', 'label' => 'End Date', 'required' => false],
                'highlight' => ['type' => 'string', 'label' => 'Highlight Text', 'required' => false],
            ],
            'cta_required' => true,
        ],

        'seasonal' => [
            'label' => 'Seasonal Drop',
            'icon' => 'calendar',
            'fields' => [
                'season' => ['type' => 'select', 'label' => 'Season', 'required' => true, 'options' => ['Spring', 'Summer', 'Fall', 'Winter', 'Holiday']],
                'collection_name' => ['type' => 'string', 'label' => 'Collection Name', 'required' => true],
                'year' => ['type' => 'number', 'label' => 'Year', 'required' => false],
                'theme' => ['type' => 'string', 'label' => 'Theme', 'required' => false],
                'limited_edition' => ['type' => 'boolean', 'label' => 'Limited Edition', 'required' => false],
            ],
            'cta_required' => true,
        ],

        'product' => [
            'label' => 'Product Showcase',
            'icon' => 'shopping-bag',
            'fields' => [
                'highlight_feature' => ['type' => 'string', 'label' => 'Highlight Feature', 'required' => false],
                'price_display' => ['type' => 'select', 'label' => 'Price Display', 'required' => false, 'options' => ['show', 'hide', 'starting_at']],
                'stock_status' => ['type' => 'string', 'label' => 'Stock Status', 'required' => false],
                'new_arrival' => ['type' => 'boolean', 'label' => 'New Arrival', 'required' => false],
            ],
            'cta_required' => false,
        ],

        'announcement' => [
            'label' => 'Announcement',
            'icon' => 'bell',
            'fields' => [
                'announcement_type' => ['type' => 'select', 'label' => 'Type', 'required' => true, 'options' => ['news', 'update', 'alert', 'info']],
                'priority' => ['type' => 'select', 'label' => 'Priority', 'required' => false, 'options' => ['low', 'medium', 'high', 'urgent']],
                'link' => ['type' => 'string', 'label' => 'Link URL', 'required' => false],
                'dismissible' => ['type' => 'boolean', 'label' => 'Dismissible', 'required' => false],
            ],
            'cta_required' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Story Type
    |--------------------------------------------------------------------------
    |
    | The default story type to use when none is specified.
    |
    */

    'default_type' => 'announcement',

    /*
    |--------------------------------------------------------------------------
    | Story Display Settings
    |--------------------------------------------------------------------------
    |
    | Global settings for story display and behavior.
    |
    */

    'display' => [
        'max_stories' => 10,
        'default_duration' => 5, // seconds
        'auto_advance' => true,
        'show_progress' => true,
    ],
];
