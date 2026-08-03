<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Enums;

enum LuckyDrawParticipantState: string
{
    case QUALIFIED = 'qualified';
    case SPOT_RESERVED = 'spot_reserved';
    case REWARD_ISSUED = 'reward_issued';
    case WINNER = 'winner';

    public function label(): string
    {
        return match ($this) {
            self::QUALIFIED => 'Qualified',
            self::SPOT_RESERVED => 'Spot Reserved',
            self::REWARD_ISSUED => 'Reward Issued',
            self::WINNER => 'Winner',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
