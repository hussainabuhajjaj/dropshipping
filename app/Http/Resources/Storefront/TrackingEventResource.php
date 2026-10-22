<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;

class TrackingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return [
                'id' => data_get($this->resource, 'id'),
                'status' => data_get($this->resource, 'status', 'Update'),
                'description' => data_get($this->resource, 'description', 'Order update'),
                'occurredAt' => data_get($this->resource, 'occurredAt'),
            ];
        }

        $occurredAt = $this->resource->occurred_at ?? $this->resource->created_at ?? null;

        return [
            'id' => $this->resource->id,
            'status' => $this->resource->status_label
                ?? $this->resource->status
                ?? $this->resource->type
                ?? 'Update',
            'description' => $this->resource->description
                ?? $this->resource->message
                ?? 'Order update',
            'occurredAt' => $occurredAt?->format('Y-m-d H:i'),
        ];
    }
}
