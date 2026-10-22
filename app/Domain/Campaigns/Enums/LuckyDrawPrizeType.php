<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Enums;

enum LuckyDrawPrizeType: string
{
    case GRAND = 'grand';
    case RUNNER_UP = 'runner_up';
    case GUARANTEED = 'guaranteed';

    public function label(): string
    {
        return match ($this) {
            self::GRAND => 'Grand Prize',
            self::RUNNER_UP => 'Runner-up',
            self::GUARANTEED => 'Guaranteed Reward',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
