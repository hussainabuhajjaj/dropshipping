<?php

namespace App\Enums;

enum ChargebackEvidenceType: string
{
    case RECEIPT = 'receipt';
    case TRACKING = 'tracking';
    case DELIVERY_PROOF = 'delivery_proof';
    case COMMUNICATION = 'communication';
    case POLICY = 'policy';
    case PRODUCT_DESCRIPTION = 'product_description';
    case CUSTOMER_CONSENT = 'customer_consent';
    case REFUND_RESPONSE = 'refund_response';
    case DISPUTE_RESPONSE = 'dispute_response';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RECEIPT => 'Receipt/Invoice',
            self::TRACKING => 'Tracking Information',
            self::DELIVERY_PROOF => 'Delivery Proof',
            self::COMMUNICATION => 'Customer Communication',
            self::POLICY => 'Store Policy',
            self::PRODUCT_DESCRIPTION => 'Product Description',
            self::CUSTOMER_CONSENT => 'Customer Consent',
            self::REFUND_RESPONSE => 'Refund Response',
            self::DISPUTE_RESPONSE => 'Dispute Response',
            self::OTHER => 'Other Evidence',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RECEIPT => 'Order receipt, invoice, or purchase confirmation',
            self::TRACKING => 'Tracking number and carrier information',
            self::DELIVERY_PROOF => 'Signature proof, delivery confirmation, or address verification',
            self::COMMUNICATION => 'Email exchanges, messages, or support tickets with customer',
            self::POLICY => 'Refund policy, terms and conditions, or return policy',
            self::PRODUCT_DESCRIPTION => 'Product details, descriptions, and specifications from time of order',
            self::CUSTOMER_CONSENT => 'Customer authorization, signature, or explicit approval',
            self::REFUND_RESPONSE => 'Refund offer, partial credit, or resolution attempt',
            self::DISPUTE_RESPONSE => 'Response to customer\'s initial dispute',
            self::OTHER => 'Other supporting evidence',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function descriptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->description()])
            ->toArray();
    }
}
