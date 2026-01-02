<?php

namespace App\Enums;

enum MessageTemplateType: string
{
    case DELAY = 'delay';
    case CUSTOMS = 'customs';
    case SPLIT_SHIPMENTS = 'split_shipments';
    case REFUND_UPDATE = 'refund_update';
    case DELIVERY_UPDATE = 'delivery_update';
    case EXCEPTION = 'exception';
    case TRACKING = 'tracking';
    case GENERAL = 'general';

    public function label(): string
    {
        return match ($this) {
            self::DELAY => 'Shipment Delay',
            self::CUSTOMS => 'Customs Clearance',
            self::SPLIT_SHIPMENTS => 'Split Shipments',
            self::REFUND_UPDATE => 'Refund Update',
            self::DELIVERY_UPDATE => 'Delivery Update',
            self::EXCEPTION => 'Exception Notification',
            self::TRACKING => 'Tracking Information',
            self::GENERAL => 'General Message',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DELAY => 'Notify customer of shipment delays',
            self::CUSTOMS => 'Inform about customs clearance requirements',
            self::SPLIT_SHIPMENTS => 'Explain multiple shipments for one order',
            self::REFUND_UPDATE => 'Update on refund status',
            self::DELIVERY_UPDATE => 'Delivery milestone notifications',
            self::EXCEPTION => 'Alert about order exceptions',
            self::TRACKING => 'Provide tracking details',
            self::GENERAL => 'Custom general purpose message',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
