<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Requests\Api\Mobile\V1\Category\CategoryShowRequest;
use App\Http\Resources\Mobile\V1\CategoryCardResource;
use App\Http\Resources\Mobile\V1\CategoryShowResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class CategoryController extends ApiController
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->withCount('products')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with([
                'children' => function ($query) {
                    $query
                        ->withCount('products')
                        ->where('is_active', true)
                        ->orderBy('name');
                },
            ])
            ->orderBy('name')
            ->get();

        return $this->success(CategoryCardResource::collection($categories));
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

        $products = $productQuery->latest()->paginate($perPage);

        return $this->success(new CategoryShowResource([
            'category' => $category,
            'products' => $products,
        ]));
    }
}
