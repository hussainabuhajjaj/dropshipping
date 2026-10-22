<?php

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Models\Order;

class ShippingQuoteService
{
    public function calculateLinehaulFee(float $totalWeightKg, float $baseFee, float $perKgRate): float
    {
        return $baseFee + ($totalWeightKg * $perKgRate);
    }

    public function calculateLastMileFee(string $zone = null): float
    {
        // For phase 1, use a flat fee (e.g., 2000 XOF)
        return 2000;
    }

    public function snapshotShippingQuote(Order $order, float $linehaulFee, float $lastMileFee, float $totalWeightKg): void
    {
        $order->update([
            'cart_total_weight_kg' => $totalWeightKg,
            'linehaul_fee' => $linehaulFee,
            'last_mile_fee' => $lastMileFee,
            'shipping_quote_snapshot' => [
                'linehaul_fee' => $linehaulFee,
                'last_mile_fee' => $lastMileFee,
                'total_weight_kg' => $totalWeightKg,
            ],
        ]);
    }
}
