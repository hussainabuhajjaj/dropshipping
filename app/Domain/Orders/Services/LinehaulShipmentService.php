<?php

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\LinehaulShipment;

class LinehaulShipmentService
{
    public function createLinehaulShipment(Order $order, float $totalWeightKg, float $baseFee, float $perKgRate): LinehaulShipment
    {
        $totalFee = $baseFee + ($totalWeightKg * $perKgRate);
        $shipment = LinehaulShipment::create([
            'order_id' => $order->id,
            'total_weight_kg' => $totalWeightKg,
            'base_fee' => $baseFee,
            'per_kg_rate' => $perKgRate,
            'total_fee' => $totalFee,
            'shipment_snapshot' => [
                'order_id' => $order->id,
                'weight' => $totalWeightKg,
                'base_fee' => $baseFee,
                'per_kg_rate' => $perKgRate,
                'total_fee' => $totalFee,
            ],
        ]);
        return $shipment;
    }

    public function markDispatched(LinehaulShipment $shipment): void
    {
        $shipment->update(['dispatched_at' => now()]);
    }

    public function markArrived(LinehaulShipment $shipment): void
    {
        $shipment->update(['arrived_at' => now()]);
    }
}
