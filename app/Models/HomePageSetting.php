<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class HomePageSetting extends Model
{
    protected $fillable = [
        'locale',
        'top_strip',
        'hero_slides',
        'rail_cards',
        'category_highlights',
        'banner_strip',
        'app_download',
    ];

    protected $casts = [
        'top_strip' => 'array',
        'hero_slides' => 'array',
        'rail_cards' => 'array',
        'category_highlights' => 'array',
        'banner_strip' => 'array',
        'app_download' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(static fn () => static::clearHomePageCache());
        static::deleted(static fn () => static::clearHomePageCache());
    }

    /**
     * Drop cached homepage payloads so settings edits (hero slides, CTA hrefs,
     * strips, etc.) reflect immediately instead of waiting for the 5-minute TTL.
     */
    public static function clearHomePageCache(): void
    {
        foreach (['fr', 'en', ''] as $locale) {
            Cache::forget('home:page:' . $locale);
        }
    }

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
