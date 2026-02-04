<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Resources\Mobile\V1\StatusResource;
use App\Http\Resources\Mobile\V1\WishlistItemResource;
use App\Models\Customer;
use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        $items = WishlistItem::query()
            ->where('customer_id', $customer->id)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->with(['product.images', 'product.variants', 'product.category', 'product.translations'])
            ->latest()
            ->get();

        return $this->success(WishlistItemResource::collection($items));
    }

    public function store(Request $request, int $productId): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        $product = Product::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->first();

        if (! $product) {
            return $this->notFound('Product not found');
        }

        $item = WishlistItem::query()->firstOrCreate([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        $item->loadMissing(['product.images', 'product.variants', 'product.category', 'product.translations']);

        return $this->success(new WishlistItemResource($item), null, 201);
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        WishlistItem::query()
            ->where('customer_id', $customer->id)
            ->where('product_id', $productId)
            ->delete();

        return $this->success(new StatusResource(['ok' => true]));
    }
}
