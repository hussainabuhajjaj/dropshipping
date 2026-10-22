<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\DTOs;

class WooCommerceOrderData
{
    public function __construct(
        public readonly int $woocommerceId,
        public readonly string $number = '',
        public readonly string $status = 'pending',
        public readonly string $currency = 'USD',
        public readonly ?float $total = null,
        public readonly ?float $subtotal = null,
        public readonly ?float $shippingTotal = null,
        public readonly ?float $taxTotal = null,
        public readonly ?float $discountTotal = null,
        public readonly ?int $customerId = null,
        public readonly string $customerEmail = '',
        public readonly ?string $paymentMethod = null,
        public readonly ?string $paymentMethodTitle = null,
        public readonly ?string $transactionId = null,
        public readonly array $lineItems = [],
        public readonly array $shippingLines = [],
        public readonly array $taxLines = [],
        public readonly array $feeLines = [],
        public readonly array $couponLines = [],
        public readonly array $billing = [],
        public readonly array $shipping = [],
        public readonly array $metaData = [],
        public readonly ?\DateTimeInterface $dateCreated = null,
        public readonly ?\DateTimeInterface $dateModified = null,
        public readonly ?\DateTimeInterface $datePaid = null,
        public readonly ?\DateTimeInterface $dateCompleted = null,
        public readonly array $rawData = [],
    ) {
    }
}
