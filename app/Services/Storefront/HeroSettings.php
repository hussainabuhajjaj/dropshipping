<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Setting;

class HeroSettings
{
    public const CAMPAIGN_OVERRIDE_KEY = 'hero_campaign_override';

    public static function campaignOverrideEnabled(): bool
    {
        $value = Setting::query()
            ->where('key', self::CAMPAIGN_OVERRIDE_KEY)
            ->value('value');

        if ($value === null) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    public static function setCampaignOverrideEnabled(bool $enabled): void
    {
        Setting::query()->updateOrCreate(
            ['key' => self::CAMPAIGN_OVERRIDE_KEY],
            ['value' => $enabled ? '1' : '0'],
        );
    }
}
