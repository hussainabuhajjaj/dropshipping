<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorefrontCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'status',
        'is_active',
        'starts_at',
        'ends_at',
        'timezone',
        'locale_visibility',
        'locale_overrides',
        'priority',
        'stacking_mode',
        'exclusive_group',
        'theme',
        'placements',
        'hero_image',
        'hero_kicker',
        'hero_subtitle',
        'content',
        'promotion_ids',
        'coupon_ids',
        'banner_ids',
        'collection_ids',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'locale_visibility' => 'array',
        'locale_overrides' => 'array',
        'theme' => 'array',
        'placements' => 'array',
        'promotion_ids' => 'array',
        'coupon_ids' => 'array',
        'banner_ids' => 'array',
        'collection_ids' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function localeOverrideMap(): array
    {
        $overrides = $this->locale_overrides ?? [];
        return collect($overrides)
            ->filter(fn ($row) => is_array($row) && ! empty($row['locale']))
            ->keyBy('locale')
            ->all();
    }

    public function localizedValue(string $field, ?string $locale): ?string
    {
        if (! $locale) {
            return $this->{$field} ?? null;
        }

        $override = $this->localeOverrideMap()[$locale] ?? null;
        if ($override && array_key_exists($field, $override) && $override[$field] !== null && $override[$field] !== '') {
            return (string) $override[$field];
        }

        return $this->{$field} ?? null;
    }

    public function isVisibleForLocale(?string $locale): bool
    {
        $allowed = $this->locale_visibility ?? [];
        if (! $allowed || ! is_array($allowed) || count($allowed) === 0) {
            return true;
        }
        if (! $locale) {
            return false;
        }
        return in_array($locale, $allowed, true);
    }

    public function resolveScheduleForLocale(?string $locale): array
    {
        $schedule = [
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'timezone' => $this->timezone,
        ];

        if (! $locale) {
            return $schedule;
        }

        $override = $this->localeOverrideMap()[$locale] ?? null;
        if (! $override || ! is_array($override)) {
            return $schedule;
        }

        return [
            'starts_at' => $override['starts_at'] ?? $schedule['starts_at'],
            'ends_at' => $override['ends_at'] ?? $schedule['ends_at'],
            'timezone' => $override['timezone'] ?? $schedule['timezone'],
        ];
    }

    public function isActiveForLocale(?string $locale, ?Carbon $now = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->isVisibleForLocale($locale)) {
            return false;
        }

        $now = $now ?: now();
        $schedule = $this->resolveScheduleForLocale($locale);
        $timezone = $schedule['timezone'] ?: config('app.timezone');
        $now = $now->copy()->timezone($timezone);

        if ($schedule['starts_at']) {
            $start = $this->parseScheduleDate($schedule['starts_at'], $timezone);
            if ($start && $now->lt($start)) {
                return false;
            }
        }

        if ($schedule['ends_at']) {
            $end = $this->parseScheduleDate($schedule['ends_at'], $timezone);
            if ($end && $now->gt($end)) {
                return false;
            }
        }

        return true;
    }

    public function promotionIds(): array
    {
        return array_values(array_filter(array_map('intval', $this->promotion_ids ?? [])));
    }

    public function couponIds(): array
    {
        return array_values(array_filter(array_map('intval', $this->coupon_ids ?? [])));
    }

    public function bannerIds(): array
    {
        return array_values(array_filter(array_map('intval', $this->banner_ids ?? [])));
    }

    public function collectionIds(): array
    {
        return array_values(array_filter(array_map('intval', $this->collection_ids ?? [])));
    }

    private function parseScheduleDate($value, string $timezone): ?Carbon
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->timezone($timezone);
        }

        try {
            return Carbon::parse($value, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }
}
