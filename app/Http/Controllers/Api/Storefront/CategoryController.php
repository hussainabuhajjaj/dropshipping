<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Storefront\Category\CategoryShowRequest;
use App\Http\Resources\Storefront\CategoryDetailResource;
use App\Http\Resources\Storefront\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use App\Http\Controllers\Storefront\Concerns\FormatsCategories;

class CategoryController extends Controller
{
    use FormatsCategories;

    public function index(): JsonResponse
    {
        $featuredOnly = request()->boolean('featured');

        return response()->json([
            'categories' => $this->rootCategoriesTree(['children', 'children.children'], $featuredOnly),
        ]);
    }

    public function show(CategoryShowRequest $request, Category $category): JsonResponse
    {
        $validated = $request->validated();
        $perPage = min((int) ($validated['per_page'] ?? 18), 50);

        $categoryIds = $this->collectDescendantCategoryIds($category);

        $productQuery = Product::query()
            ->where('is_active', true)
            ->whereIn('category_id', $categoryIds)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        $products = $productQuery
            ->orderByRaw('MD5(CONCAT(products.id, ?))', [now()->toDateString()])
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

    /**
     * Collect the selected category id plus all descendant ids (children, grandchildren, etc.).
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    private function collectDescendantCategoryIds(Category $root): Collection
    {
        $ids = collect([$root->id]);

        $queue = collect([$root->id]);
        while ($queue->isNotEmpty()) {
            $children = Category::query()
                ->whereIn('parent_id', $queue->all())
                ->pluck('id');

            $queue = $children;
            $ids = $ids->merge($children);
        }

        return $ids->unique()->values();
    }
}
