<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use App\Support\ResolvesStorefrontVariantLabels;
use Illuminate\Http\Request;

class OrderItemResource extends JsonResource
{
    use ResolvesStorefrontVariantLabels;

    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return [
                'id' => data_get($this->resource, 'id'),
                'name' => data_get($this->resource, 'name', 'Item'),
                'variant' => data_get($this->resource, 'variant'),
                'quantity' => (int) (data_get($this->resource, 'quantity') ?? 1),
                'price' => (float) (data_get($this->resource, 'price') ?? 0),
                'currency' => (string) (data_get($this->resource, 'currency') ?? 'USD'),
                'image' => data_get($this->resource, 'image'),
            ];
        }

        $product = $this->resource->productVariant?->product;
        $image = $product?->images?->sortBy('position')->first()?->url;
        $snapshot = is_array($this->resource->snapshot ?? null) ? $this->resource->snapshot : [];
        $variantValue = $snapshot['variant'] ?? null;
        $variantLabel = is_string($variantValue)
            ? $variantValue
            : (is_array($variantValue) ? implode(' / ', array_filter($variantValue, 'is_scalar')) : null);

        if (! $variantLabel) {
            $variantLabel = $snapshot['variant_key']
                ?? $snapshot['variant_name']
                ?? ($this->resource->productVariant
                    ? $this->resolveVariantDisplayTitle(
                        $this->resource->productVariant,
                        $this->resource->productVariant->title,
                        $product?->name
                    )
                    : null);
        }

        return [
            'id' => $this->resource->id,
            'name' => $snapshot['name'] ?? $product?->name ?? 'Item',
            'variant' => $variantLabel,
            'quantity' => (int) $this->resource->quantity,
            'price' => (float) $this->resource->unit_price,
            'currency' => (string) ($this->resource->currency ?? $this->resource->order?->currency ?? 'USD'),
            'image' => $image,
        ];
    }
}
