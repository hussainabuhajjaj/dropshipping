<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Product;
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
