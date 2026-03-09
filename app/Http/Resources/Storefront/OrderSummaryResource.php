<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use App\Domain\Payments\Models\Payment;
use Illuminate\Http\Request;

class OrderSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rawStatus = $this->resource->customer_status ?? $this->resource->status ?? data_get($this->resource, 'status');
        $internalStatuses = ['pending', 'paid', 'fulfilling', 'fulfilled', 'cancelled'];
        $statusKey = in_array($rawStatus, $internalStatuses, true)
            ? match ($rawStatus) {
                'pending', 'paid' => 'received',
                'fulfilling', 'fulfilled' => 'processing',
                'cancelled' => 'refunded',
                default => 'processing',
            }
            : ($rawStatus ?? 'processing');

        $payment = $this->resolveLatestPayment();

        return [
            'number' => $this->resource->number ?? data_get($this->resource, 'number'),
            'status' => method_exists($this->resource, 'getCustomerStatusLabel')
                ? $this->resource->getCustomerStatusLabel()
                : (data_get($this->resource, 'status') ?? 'Processing'),
            'statusKey' => $statusKey,
            'currency' => (string) ($this->resource->currency ?? data_get($this->resource, 'currency') ?? 'USD'),
            'subtotal' => (float) ($this->resource->subtotal ?? data_get($this->resource, 'subtotal') ?? 0),
            'shippingTotal' => (float) ($this->resource->shipping_total ?? data_get($this->resource, 'shipping_total') ?? 0),
            'taxTotal' => (float) ($this->resource->tax_total ?? data_get($this->resource, 'tax_total') ?? 0),
            'discountTotal' => (float) ($this->resource->discount_total ?? data_get($this->resource, 'discount_total') ?? 0),
            'total' => (float) ($this->resource->grand_total ?? data_get($this->resource, 'total') ?? 0),
            'paidAmount' => $payment?->amount !== null ? (float) $payment->amount : null,
            'paidCurrency' => $payment?->currency,
            'placedAt' => $this->resource->placed_at?->toDateString() ?? data_get($this->resource, 'placedAt'),
        ];
    }

    private function resolveLatestPayment(): ?Payment
    {
        $order = $this->resource;
        $payments = $order->relationLoaded('payments')
            ? collect($order->payments)
            : $order->payments()->latest('paid_at')->latest('id')->get();

        $preferred = $payments->first(
            fn ($payment) => in_array((string) $payment->status, ['paid', 'success'], true)
        );

        return $preferred ?: $payments->first();
    }
}
