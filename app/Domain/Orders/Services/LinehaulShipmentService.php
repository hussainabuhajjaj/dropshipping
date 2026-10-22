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

    public function createOrUpdateFromQuote(Order $order): ?LinehaulShipment
    {
        $snapshot = is_array($order->shipping_quote_snapshot) ? $order->shipping_quote_snapshot : [];
        $totalWeightKg = (float) ($order->cart_total_weight_kg ?? $snapshot['total_weight_kg'] ?? 0);
        $linehaulFee = (float) ($order->linehaul_fee ?? $snapshot['linehaul_fee'] ?? 0);

        if ($totalWeightKg <= 0 && $linehaulFee <= 0) {
            return null;
        }

        return LinehaulShipment::query()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'tracking_number' => null,
                'total_weight_kg' => $totalWeightKg,
                // Legacy quote snapshots do not store a fee breakdown.
                'base_fee' => $linehaulFee,
                'per_kg_rate' => 0,
                'total_fee' => $linehaulFee,
                'shipment_snapshot' => array_filter([
                    'source' => 'order_quote',
                    'quote' => $snapshot,
                ]),
            ]
        );
    }

    /**
     * @param array<string, mixed> $cjOrder
     */
    public function createOrUpdateFromCjOrder(Order $order, array $cjOrder): LinehaulShipment
    {
        $weight = $this->normalizeWeightKg($cjOrder['orderWeight'] ?? null)
            ?? (float) ($order->cart_total_weight_kg ?? 0);
        $postage = is_numeric($cjOrder['postageAmount'] ?? null)
            ? (float) $cjOrder['postageAmount']
            : (float) ($order->linehaul_fee ?? 0);

        $shipment = LinehaulShipment::query()->firstOrNew(['order_id' => $order->id]);
        $shipment->fill([
            'tracking_number' => $cjOrder['trackNumber'] ?? $shipment->tracking_number,
            'total_weight_kg' => $weight,
            'base_fee' => $shipment->exists ? $shipment->base_fee : $postage,
            'per_kg_rate' => $shipment->exists ? $shipment->per_kg_rate : 0,
            'total_fee' => $postage,
        ]);
        $shipment->applyCjOrder($cjOrder);
        $shipment->save();

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

    private function normalizeWeightKg(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $weight = (float) $value;

        if ($weight <= 0) {
            return null;
        }

        // CJ order weight is often returned in grams.
        return $weight > 100 ? round($weight / 1000, 3) : round($weight, 3);
    }
}
