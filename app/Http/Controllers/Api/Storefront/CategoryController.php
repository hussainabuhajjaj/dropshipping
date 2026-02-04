<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Storefront\Category\CategoryShowRequest;
use App\Http\Resources\Storefront\CategoryCardResource;
use App\Http\Resources\Storefront\CategoryDetailResource;
use App\Http\Resources\Storefront\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{

    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->withCount('products')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'categories' => CategoryCardResource::collection($categories),
        ]);
    }

    public function show(CategoryShowRequest $request, Category $category): JsonResponse
    {
        $validated = $request->validated();
        $perPage = min((int) ($validated['per_page'] ?? 18), 50);

        $productQuery = Product::query()
            ->where('is_active', true)
            ->where('category_id', $category->id)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        $products = $productQuery
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'category' => new CategoryDetailResource($category),
            'products' => ProductResource::collection($products->getCollection()),
            'pagination' => [
                'currentPage' => $products->currentPage(),
                'lastPage' => $products->lastPage(),
                'perPage' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }
}
