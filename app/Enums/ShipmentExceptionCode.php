<?php

namespace App\Enums;

use Illuminate\Support\Traits\Macroable;

enum ShipmentExceptionCode: string
{

    // Customs & Border Issues
    case CUSTOMS_DUTY_UNPAID = 'customs_duty_unpaid';
    case CUSTOMS_CLEARANCE_REQUIRED = 'customs_clearance_required';
    case CUSTOMS_HELD = 'customs_held';
    case CUSTOMS_DOCUMENTATION_MISSING = 'customs_documentation_missing';

    // Delivery Failures
    case RETURNED_TO_SENDER = 'returned_to_sender';
    case DELIVERY_FAILED = 'delivery_failed';
    case ADDRESS_INVALID = 'address_invalid';
    case RECIPIENT_REFUSED = 'recipient_refused';
    case DELIVERY_ATTEMPTED_NO_RESPONSE = 'delivery_attempted_no_response';

    // Tracking Issues
    case TRACKING_NO_UPDATES = 'tracking_no_updates';
    case TRACKING_LOST = 'tracking_lost';
    case TRACKING_DELAYED = 'tracking_delayed';

    // Damage & Loss
    case PACKAGE_DAMAGED = 'package_damaged';
    case PACKAGE_LOST = 'package_lost';
    case PARTIAL_DELIVERY = 'partial_delivery';

    // Other Issues
    case CARRIER_ERROR = 'carrier_error';
    case WEATHER_DELAY = 'weather_delay';
    case MECHANICAL_FAILURE = 'mechanical_failure';
    case SYSTEM_ERROR = 'system_error';
    case UNKNOWN = 'unknown';

    public static function labels(): array
    {
        return [
            self::CUSTOMS_DUTY_UNPAID->value => 'Customs Duty Unpaid',
            self::CUSTOMS_CLEARANCE_REQUIRED->value => 'Customs Clearance Required',
            self::CUSTOMS_HELD->value => 'Held in Customs',
            self::CUSTOMS_DOCUMENTATION_MISSING->value => 'Customs Documentation Missing',

            self::RETURNED_TO_SENDER->value => 'Returned to Sender',
            self::DELIVERY_FAILED->value => 'Delivery Failed',
            self::ADDRESS_INVALID->value => 'Invalid Address',
            self::RECIPIENT_REFUSED->value => 'Recipient Refused',
            self::DELIVERY_ATTEMPTED_NO_RESPONSE->value => 'Delivery Attempted - No Response',

            self::TRACKING_NO_UPDATES->value => 'No Tracking Updates',
            self::TRACKING_LOST->value => 'Tracking Lost',
            self::TRACKING_DELAYED->value => 'Tracking Delayed',

            self::PACKAGE_DAMAGED->value => 'Package Damaged',
            self::PACKAGE_LOST->value => 'Package Lost',
            self::PARTIAL_DELIVERY->value => 'Partial Delivery',

            self::CARRIER_ERROR->value => 'Carrier Error',
            self::WEATHER_DELAY->value => 'Weather Delay',
            self::MECHANICAL_FAILURE->value => 'Mechanical Failure',
            self::SYSTEM_ERROR->value => 'System Error',
            self::UNKNOWN->value => 'Unknown',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value] ?? $this->value;
    }

    public function severity(): string
    {
        return match ($this) {
            self::CUSTOMS_DUTY_UNPAID,
            self::RETURNED_TO_SENDER,
            self::PACKAGE_LOST,
            self::DELIVERY_FAILED => 'critical',

            self::CUSTOMS_CLEARANCE_REQUIRED,
            self::CUSTOMS_HELD,
            self::ADDRESS_INVALID,
            self::RECIPIENT_REFUSED,
            self::TRACKING_LOST,
            self::PACKAGE_DAMAGED => 'high',

            self::TRACKING_NO_UPDATES,
            self::TRACKING_DELAYED,
            self::DELIVERY_ATTEMPTED_NO_RESPONSE,
            self::WEATHER_DELAY,
            self::MECHANICAL_FAILURE => 'medium',

            default => 'low',
        };
    }

    public function isCritical(): bool
    {
        return $this->severity() === 'critical';
    }

    public function isCustomsIssue(): bool
    {
        return in_array($this, [
            self::CUSTOMS_DUTY_UNPAID,
            self::CUSTOMS_CLEARANCE_REQUIRED,
            self::CUSTOMS_HELD,
            self::CUSTOMS_DOCUMENTATION_MISSING,
        ]);
    }

    public function isDeliveryFailure(): bool
    {
        return in_array($this, [
            self::RETURNED_TO_SENDER,
            self::DELIVERY_FAILED,
            self::ADDRESS_INVALID,
            self::RECIPIENT_REFUSED,
            self::DELIVERY_ATTEMPTED_NO_RESPONSE,
        ]);
    }

    public function isTrackingIssue(): bool
    {
        return in_array($this, [
            self::TRACKING_NO_UPDATES,
            self::TRACKING_LOST,
            self::TRACKING_DELAYED,
        ]);
    }
}
