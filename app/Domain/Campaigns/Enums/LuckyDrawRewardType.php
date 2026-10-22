<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Enums;

enum LuckyDrawRewardType: string
{
    case FREE_SHIPPING = 'free_shipping';
    case PERCENTAGE_DISCOUNT = 'percentage_discount';
    case FIXED_DISCOUNT = 'fixed_discount';
    case STORE_CREDIT = 'store_credit';
    case COUPON_CODE = 'coupon_code';

    public function label(): string
    {
        return match ($this) {
            self::FREE_SHIPPING => 'Free Shipping',
            self::PERCENTAGE_DISCOUNT => 'Percentage Discount',
            self::FIXED_DISCOUNT => 'Fixed Discount',
            self::STORE_CREDIT => 'Store Credit',
            self::COUPON_CODE => 'Coupon Code',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
