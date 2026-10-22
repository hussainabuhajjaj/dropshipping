<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Orders\Models\OrderItem;
use App\Domain\Products\Models\Product;
use App\Models\Customer;
use Illuminate\Support\Collection;

class ProductRecommendationService
{
    public function relatedProducts(Product $product, int $limit = 4): Collection
    {
        $query = Product::query()
            ->where('is_active', true)
            ->whereKeyNot($product->id)
            ->with(['images', 'category', 'translations'])
            ->orderByDesc('selling_price');

        if ($product->category_id) {
            $query->where('category_id', $product->category_id);
        }

        $products = $query->take($limit)->get();

        if ($products->count() < $limit) {
            $fallback = Product::query()
                ->where('is_active', true)
                ->whereKeyNot($product->id)
                ->latest()
                ->take($limit - $products->count())
                ->get();
            $products = $products->merge($fallback);
        }

        return $products->take($limit);
    }

    public function frequentlyBoughtTogether(Product $product, int $limit = 3): Collection
    {
        $variantIds = $product->variants()->pluck('product_variants.id');

        if ($variantIds->isEmpty()) {
            return collect();
        }

        $frequentlyBoughtIds = OrderItem::query()
            ->selectRaw('product_variant_id, COUNT(*) as frequency')
            ->whereIn('order_id', function ($query) use ($variantIds) {
                $query->select('order_id')
                    ->from('order_items')
                    ->whereIn('product_variant_id', $variantIds);
            })
            ->whereNotIn('product_variant_id', $variantIds)
            ->groupBy('product_variant_id')
            ->orderByDesc('frequency')
            ->limit($limit * 2)
            ->pluck('product_variant_id');

        if ($frequentlyBoughtIds->isEmpty()) {
            return collect();
        }

        $products = Product::query()
            ->whereHas('variants', function ($query) use ($frequentlyBoughtIds) {
                $query->whereIn('product_variants.id', $frequentlyBoughtIds);
            })
            ->where('is_active', true)
            ->whereKeyNot($product->id)
            ->with(['images', 'category', 'translations'])
            ->take($limit)
            ->get();

        return $products;
    }

    public function personalized(Customer $customer, int $limit = 6): Collection
    {
        $recentProductIds = OrderItem::query()
            ->whereHas('order', function ($builder) use ($customer) {
                $builder->where('customer_id', $customer->id)->where('payment_status', 'paid');
            })
            ->latest()
            ->limit(20)
            ->pluck('product_variant_id');

        $products = Product::query()
            ->whereHas('variants', function ($builder) use ($recentProductIds) {
                $builder->whereIn('product_variants.id', $recentProductIds);
            })
            ->where('is_active', true)
            ->with(['images', 'category', 'translations'])
            ->take($limit)
            ->get();

        if ($products->count() < $limit) {
            $fallback = Product::query()
                ->where('is_active', true)
                ->latest()
                ->take($limit - $products->count())
                ->get();
            $products = $products->merge($fallback);
        }

        return $products->take($limit);
    }
}
