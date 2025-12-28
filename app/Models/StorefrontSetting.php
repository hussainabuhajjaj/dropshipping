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
    ];

    protected $casts = [
        'header_links' => 'array',
        'footer_columns' => 'array',
        'value_props' => 'array',
        'social_links' => 'array',
    ];
}
