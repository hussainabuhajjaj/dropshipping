<?php

declare(strict_types=1);

namespace App\Domain\Products\DTOs;

class PricingResultDTO
{
    public function __construct(
        public float $costPrice,
        public float $weightKg,
        public float $cjShipping,
        public ?int $warehouseId,
        public float $shippingRatePerKg,
        public float $externalShipping,
        public float $landedCost,
        public float $basePrice,
        public string $currency,
        public float $marginPercent,
        public array $pricingMeta = [],
    ) {
    }
}
