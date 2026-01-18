<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorefrontSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_name',
        'footer_blurb',
        'delivery_notice',
        'copyright_text',
        'header_links',
        'footer_columns',
        'value_props',
        'social_links',
        'coming_soon_enabled',
        'coming_soon_title',
        'coming_soon_message',
        'coming_soon_image',
        'coming_soon_cta_label',
        'coming_soon_cta_url',
        'newsletter_popup_enabled',
        'newsletter_popup_title',
        'newsletter_popup_body',
        'newsletter_popup_incentive',
        'newsletter_popup_image',
        'newsletter_popup_delay_seconds',
        'newsletter_popup_dismiss_days',
    ];

    protected $casts = [
        'header_links' => 'array',
        'footer_columns' => 'array',
        'value_props' => 'array',
        'social_links' => 'array',
        'coming_soon_enabled' => 'boolean',
        'newsletter_popup_enabled' => 'boolean',
        'newsletter_popup_delay_seconds' => 'int',
        'newsletter_popup_dismiss_days' => 'int',
    ];
}
