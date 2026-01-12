<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $variant = $this->variant;
        return [
            'id' => $this['id'],
            'product_id' => $this['product_id'],
            'variant_id' => $this['variant_id'],
            'fulfillment_provider_id' => $this['fulfillment_provider_id'],
            'quantity' => $this['quantity'],
            'stock_on_hand' => $this['stock_on_hand'],
            'name' => $product?->name,
            'variant' => $variant?->title,
            'price' => $this->getSinglePrice(),
            'currency' => $variant?->currency ?? $product->currency ?? 'USD',
            'media' => $product->images?->sortBy('position')->pluck('url')->values()->all() ?? [],
            'sku' => $variant?->sku,
            'cj_pid' => $product->attributes['cj_pid'] ?? null,
            'cj_vid' => $variant?->metadata['cj_vid'] ?? null,
        ];
    }
}
