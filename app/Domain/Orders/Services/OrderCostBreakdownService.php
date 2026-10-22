<?php

declare(strict_types=1);

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Shipment;
use App\Services\Currency\CurrencyConversionService;

class OrderCostBreakdownService
{
    public function recalculate(Order $order): Order
    {
        $order->loadMissing([
            'orderItems.productVariant.product',
            'shipments.items',
        ]);

        $currencyConverter = app(CurrencyConversionService::class);
        $orderCurrency = (string) ($order->currency ?? config('currency.base', 'USD'));

        $itemBreakdown = $order->orderItems->map(fn (OrderItem $item) => $this->buildItemBreakdown($item, $orderCurrency, $currencyConverter));

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

    private function buildItemBreakdown(OrderItem $item, string $orderCurrency, CurrencyConversionService $currencyConverter): array
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

        $costCurrency = $this->resolveCostCurrency($item);
        $productCostUnitInOrderCurrency = $this->convertCostToOrderCurrency($unitProductCost, $costCurrency, $orderCurrency, $currencyConverter);
        $externalShippingUnitInOrderCurrency = $this->convertCostToOrderCurrency($unitExternalShipping, $costCurrency, $orderCurrency, $currencyConverter);

        return [
            'order_item_id' => $item->id,
            'sku' => $item->source_sku,
            'quantity' => $quantity,
            'cost_currency' => $costCurrency,
            'product_cost_unit' => round($productCostUnitInOrderCurrency, 2),
            'product_cost_total' => round($productCostUnitInOrderCurrency * $quantity, 2),
            'external_shipping_unit' => round($externalShippingUnitInOrderCurrency, 2),
            'external_shipping_total' => round($externalShippingUnitInOrderCurrency * $quantity, 2),
            'revenue_total' => round((float) ($item->total ?? 0), 2),
        ];
    }

    private function resolveCostCurrency(OrderItem $item): string
    {
        $variantCurrency = (string) ($item->productVariant?->currency ?? '');
        $productCurrency = (string) ($item->productVariant?->product?->currency ?? '');

        return $variantCurrency ?: $productCurrency ?: config('currency.base', 'USD');
    }

    private function convertCostToOrderCurrency(
        float $amount,
        string $sourceCurrency,
        string $orderCurrency,
        CurrencyConversionService $currencyConverter
    ): float {
        if ($amount <= 0 || $sourceCurrency === $orderCurrency) {
            return $amount;
        }

        return $currencyConverter->convertAmount($amount, $sourceCurrency, $orderCurrency) ?? $amount;
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
