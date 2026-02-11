<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomePageSetting extends Model
{
    protected $fillable = [
        'locale',
        'top_strip',
        'hero_slides',
        'rail_cards',
        'category_highlights',
        'banner_strip',
    ];

    protected $casts = [
        'top_strip' => 'array',
        'hero_slides' => 'array',
        'rail_cards' => 'array',
        'category_highlights' => 'array',
        'banner_strip' => 'array',
    ];

    public static function latestForLocale(?string $locale): ?self
    {
        $locale = is_string($locale) ? strtolower(trim($locale)) : null;

        if ($locale) {
            $record = static::query()
                ->where('locale', $locale)
                ->latest()
                ->first();

            if ($record) {
                return $record;
            }
        }

        $default = static::query()
            ->whereNull('locale')
            ->latest()
            ->first();

        return $default ?: static::query()->latest()->first();
    }
}
