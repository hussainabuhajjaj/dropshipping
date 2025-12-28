<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use TransformsProducts;

    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $query = $request->query('q');

        $productQuery = Product::query()
            ->where('is_active', true)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($category) {
            $productQuery->whereHas('category', function ($builder) use ($category) {
                $builder->where('name', $category)->orWhere('slug', $category);
            });
        }

        if ($minPrice !== null && is_numeric($minPrice)) {
            $productQuery->where('selling_price', '>=', (float) $minPrice);
        }

        if ($maxPrice !== null && is_numeric($maxPrice)) {
            $productQuery->where('selling_price', '<=', (float) $maxPrice);
        }

        if ($query) {
            $productQuery->where(function ($builder) use ($query) {
                $builder
                    ->where('name', 'like', '%' . $query . '%')
                    ->orWhere('description', 'like', '%' . $query . '%');
                $builder->orWhereHas('category', function ($categoryBuilder) use ($query) {
                    $categoryBuilder->where('name', 'like', '%' . $query . '%');
                });
            });
        }

        $perPage = min((int) $request->query('per_page', 18), 50);
        $products = $productQuery
            ->latest()
            ->paginate($perPage);

        $products->getCollection()->transform(fn (Product $product) => $this->transformProduct($product));

        return response()->json([
            'products' => $products->items(),
            'pagination' => [
                'currentPage' => $products->currentPage(),
                'lastPage' => $products->lastPage(),
                'perPage' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        abort_if(! $product->is_active, 404);

        $product->load(['images', 'variants', 'category', 'translations']);

        return response()->json([
            'product' => $this->transformProduct($product, true),
        ]);
    }
}
