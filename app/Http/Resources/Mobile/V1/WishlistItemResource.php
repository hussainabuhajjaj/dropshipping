<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

use App\Models\WishlistItem;
use Illuminate\Http\Request;

class WishlistItemResource extends \App\Http\Resources\Storefront\JsonResource
{
    use WithoutSuccessWrapper;
    public function toArray(Request $request): array
    {
        /** @var WishlistItem $item */
        $item = $this->resource;
        $product = $item->product;
        $productPayload = $product ? (new ProductResource($product))->toArray($request) : null;

        if (is_array($productPayload)) {
            $productPayload['is_in_wishlist'] = true;
        }

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'added_at' => $item->created_at?->toIso8601String(),
            'product' => $productPayload,
        ];
    }
}
