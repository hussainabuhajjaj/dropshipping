<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type',
        'value_type',
        'value',
        'start_at',
        'end_at',
        'priority',
        'is_active',
        'stacking_rule',
        'locale_overrides',
        'promotion_intent',
        'display_placements',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'value' => 'float',
        'display_placements' => 'array',
        'locale_overrides' => 'array',
    ];

    public function targets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(PromotionCondition::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
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
}
