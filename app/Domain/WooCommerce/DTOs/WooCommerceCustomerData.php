<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\DTOs;

class WooCommerceCustomerData
{
    public function __construct(
        public readonly int $woocommerceId,
        public readonly string $email = '',
        public readonly string $firstName = '',
        public readonly string $lastName = '',
        public readonly ?string $phone = null,
        public readonly ?string $vat = null,
        public readonly ?string $country = null,
        public readonly ?string $state = null,
        public readonly ?string $city = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $addressLine1 = null,
        public readonly ?string $addressLine2 = null,
        public readonly array $billing = [],
        public readonly array $shipping = [],
        public readonly array $metaData = [],
        public readonly array $rawData = [],
    ) {
    }
}
