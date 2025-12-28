<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'sku' => $this->sku,
            'selling_price' => [
                'amount' => $this->selling_price,
                'formatted' => number_format($this->selling_price, 2),
                'currency' => 'USD',
            ],
            'cost_price' => $this->when(
                $request->user()?->tokenCan('products:view-costs'),
                fn() => [
                    'amount' => $this->cost_price,
                    'formatted' => number_format($this->cost_price, 2),
                    'currency' => 'USD',
                ]
            ),
            'stock_quantity' => $this->stock_quantity,
            'is_active' => (bool) $this->is_active,
            'featured' => (bool) $this->featured,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->url,
                        'alt' => $image->alt_text,
                        'is_primary' => (bool) $image->is_primary,
                    ];
                });
            }),
            'meta' => [
                'views' => $this->views_count ?? 0,
                'sales' => $this->sales_count ?? 0,
            ],
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
