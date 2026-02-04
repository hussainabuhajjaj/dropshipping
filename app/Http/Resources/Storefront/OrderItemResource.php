<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return [
                'id' => data_get($this->resource, 'id'),
                'name' => data_get($this->resource, 'name', 'Item'),
                'quantity' => (int) (data_get($this->resource, 'quantity') ?? 1),
                'price' => (float) (data_get($this->resource, 'price') ?? 0),
                'image' => data_get($this->resource, 'image'),
            ];
        }

        $product = $this->resource->productVariant?->product;
        $image = $product?->images?->sortBy('position')->first()?->url;

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->snapshot['name'] ?? $product?->name ?? 'Item',
            'quantity' => (int) $this->resource->quantity,
            'price' => (float) $this->resource->unit_price,
            'image' => $image,
        ];
    }
}
