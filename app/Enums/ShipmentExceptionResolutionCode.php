<?php

namespace App\Enums;

enum ShipmentExceptionResolutionCode: string
{
    // Customs resolutions
    case CUSTOMS_CLEARED = 'customs_cleared';
    case CUSTOMS_DUTY_PAID = 'customs_duty_paid';
    case CUSTOMS_WAIVED = 'customs_waived';

    // Delivery resolutions
    case RESHIPPED = 'reshipped';
    case REFUNDED = 'refunded';
    case PARTIAL_REFUND = 'partial_refund';
    case ADDRESS_CORRECTED = 'address_corrected';
    case REDELIVERY_SCHEDULED = 'redelivery_scheduled';

    // Loss/Damage resolutions
    case INSURANCE_CLAIM_FILED = 'insurance_claim_filed';
    case CARRIER_REIMBURSEMENT = 'carrier_reimbursement';
    case REPLACEMENT_SENT = 'replacement_sent';

    // Tracking resolutions
    case TRACKING_RECOVERED = 'tracking_recovered';
    case MANUALLY_UPDATED = 'manually_updated';
    case AWAITING_UPDATE = 'awaiting_update';

    // General resolutions
    case INVESTIGATING = 'investigating';
    case RESOLVED = 'resolved';
    case UNABLE_TO_RESOLVE = 'unable_to_resolve';

    public static function labels(): array
    {
        return [
            self::CUSTOMS_CLEARED->value => 'Customs Cleared',
            self::CUSTOMS_DUTY_PAID->value => 'Customs Duty Paid',
            self::CUSTOMS_WAIVED->value => 'Customs Duty Waived',

            self::RESHIPPED->value => 'Reshipped to Customer',
            self::REFUNDED->value => 'Full Refund Processed',
            self::PARTIAL_REFUND->value => 'Partial Refund Processed',
            self::ADDRESS_CORRECTED->value => 'Address Corrected',
            self::REDELIVERY_SCHEDULED->value => 'Redelivery Scheduled',

            self::INSURANCE_CLAIM_FILED->value => 'Insurance Claim Filed',
            self::CARRIER_REIMBURSEMENT->value => 'Carrier Reimbursement',
            self::REPLACEMENT_SENT->value => 'Replacement Sent',

            self::TRACKING_RECOVERED->value => 'Tracking Recovered',
            self::MANUALLY_UPDATED->value => 'Manually Updated',
            self::AWAITING_UPDATE->value => 'Awaiting Update',

            self::INVESTIGATING->value => 'Investigating',
            self::RESOLVED->value => 'Resolved',
            self::UNABLE_TO_RESOLVE->value => 'Unable to Resolve',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value] ?? $this->value;
    }
}
