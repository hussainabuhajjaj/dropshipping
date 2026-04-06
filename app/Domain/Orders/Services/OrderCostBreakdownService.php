<?php

declare(strict_types=1);

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Shipment;

class OrderCostBreakdownService
{
    public function recalculate(Order $order): Order
    {
        $order->loadMissing([
            'orderItems.productVariant.product',
            'shipments.items',
        ]);

        $itemBreakdown = $order->orderItems->map(fn (OrderItem $item) => $this->buildItemBreakdown($item));

        $productCostTotal = round((float) $itemBreakdown->sum('product_cost_total'), 2);
        $externalShippingTotal = round((float) $itemBreakdown->sum('external_shipping_total'), 2);

        $actualCjShipping = $this->actualCjShippingTotal($order);
        $estimatedCjShipping = $this->estimatedCjShippingTotal($order);
        $cjShippingTotal = $actualCjShipping ?? $estimatedCjShipping ?? 0.0;

        $supplierTotalCost = round($productCostTotal + $externalShippingTotal + $cjShippingTotal, 2);
        $grandTotal = (float) ($order->grand_total ?? 0);
        $grossProfit = round($grandTotal - $supplierTotalCost, 2);
        $grossMarginPercent = $grandTotal > 0 ? round(($grossProfit / $grandTotal) * 100, 2) : null;

        $order->update([
            'supplier_product_cost_total' => $productCostTotal,
            'supplier_external_shipping_total' => $externalShippingTotal,
            'supplier_cj_shipping_total' => round($cjShippingTotal, 2),
            'supplier_total_cost' => $supplierTotalCost,
            'gross_profit_amount' => $grossProfit,
            'gross_margin_percent' => $grossMarginPercent,
            'cost_breakdown' => [
                'currency' => $order->currency,
                'cj_shipping_source' => $actualCjShipping !== null ? 'actual_shipment_postage' : ($estimatedCjShipping !== null ? 'estimated_cj_amount_due' : 'none'),
                'items' => $itemBreakdown->values()->all(),
            ],
            'cost_calculated_at' => now(),
        ]);

        return $order->fresh();
    }

    private function buildItemBreakdown(OrderItem $item): array
    {
        $variant = $item->productVariant;
        $product = $variant?->product;
        $quantity = (int) ($item->quantity ?? 0);

        $unitProductCost = $this->normalizeMoney($variant?->cost_price)
            ?? $this->normalizeMoney($product?->cost_price)
            ?? 0.0;

        $unitExternalShipping = $this->normalizeMoney(data_get($variant?->metadata, 'pricing_meta.external_shipping'))
            ?? $this->normalizeMoney(data_get($variant?->metadata, 'external_shipping'))
            ?? $this->normalizeMoney(data_get($product?->pricing_meta, 'external_shipping'))
            ?? 0.0;

        return [
            'order_item_id' => $item->id,
            'sku' => $item->source_sku,
            'quantity' => $quantity,
            'product_cost_unit' => round($unitProductCost, 2),
            'product_cost_total' => round($unitProductCost * $quantity, 2),
            'external_shipping_unit' => round($unitExternalShipping, 2),
            'external_shipping_total' => round($unitExternalShipping * $quantity, 2),
            'revenue_total' => round((float) ($item->total ?? 0), 2),
        ];
    }

    private function actualCjShippingTotal(Order $order): ?float
    {
        if ($order->shipments->isEmpty()) {
            return null;
        }

        $sum = $order->shipments->sum(fn (Shipment $shipment) => (float) ($shipment->postage_amount ?? 0));

        return $sum > 0 ? round((float) $sum, 2) : null;
    }

    private function estimatedCjShippingTotal(Order $order): ?float
    {
        $cjAmountDue = $this->normalizeMoney($order->cj_amount_due);

        return $cjAmountDue !== null && $cjAmountDue > 0 ? round($cjAmountDue, 2) : null;
    }

    private function normalizeMoney(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
