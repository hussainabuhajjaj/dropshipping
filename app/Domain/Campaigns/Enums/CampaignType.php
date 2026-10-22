<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Enums;

enum CampaignType: string
{
    case SEASONAL = 'seasonal';
    case DROP = 'drop';
    case EVENT = 'event';
    case LUCKY_DRAW = 'lucky_draw';

    public function label(): string
    {
        return match ($this) {
            self::SEASONAL => 'Seasonal',
            self::DROP => 'Drop',
            self::EVENT => 'Event',
            self::LUCKY_DRAW => 'Lucky Draw',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
