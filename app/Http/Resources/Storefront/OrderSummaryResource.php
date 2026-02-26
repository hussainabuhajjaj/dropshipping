<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

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

        return [
            'number' => $this->resource->number ?? data_get($this->resource, 'number'),
            'status' => method_exists($this->resource, 'getCustomerStatusLabel')
                ? $this->resource->getCustomerStatusLabel()
                : (data_get($this->resource, 'status') ?? 'Processing'),
            'statusKey' => $statusKey,
            'total' => (float) ($this->resource->grand_total ?? data_get($this->resource, 'total') ?? 0),
            'placedAt' => $this->resource->placed_at?->toDateString() ?? data_get($this->resource, 'placedAt'),
        ];
    }
}
