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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CategoryController extends Controller
{

    public function index(): JsonResponse
    {
        $locale = app()->getLocale();
        $featuredOnly = request()->boolean('featured');

        if ($featuredOnly) {
            $flagColumn = Schema::hasColumn('categories', 'is_featured')
                ? 'is_featured'
                : (Schema::hasColumn('categories', 'is_feature') ? 'is_feature' : null);

            if (! $flagColumn) {
                return response()->json(['categories' => []]);
            }

            $idSignature = Category::query()
                ->where($flagColumn, true)
                ->orderBy('id')
                ->pluck('id')
                ->implode(',');

            $cacheKey = "featured-categories:{$locale}:" . md5($idSignature);

            $categories = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($locale) {
                $flagColumn = Schema::hasColumn('categories', 'is_featured')
                    ? 'is_featured'
                    : (Schema::hasColumn('categories', 'is_feature') ? 'is_feature' : null);

                $query = Category::query()
                    ->where('is_active', true)
                    ->where($flagColumn, true)
                    ->select(['id', 'name', 'slug', 'hero_image', 'parent_id', 'created_at', $flagColumn . ' as is_featured'])
                    ->with(['translations' => fn ($q) => $q->where('locale', $locale)]);

                if (Schema::hasColumn('categories', 'featured_order')) {
                    $query->orderByRaw('featured_order is null')->orderBy('featured_order');
                }

                $query->orderBy('created_at')->orderBy('name');

                return $query->get()->map(function (Category $category) use ($locale) {
                    return [
                        'category_id' => $category->id,
                        'category_name' => $category->translatedValue('name', $locale),
                        'slug' => $category->slug,
                        'category_image' => $category->hero_image,
                        'parent_category_id' => $category->parent_id,
                    ];
                })->values();
            });

            return response()->json([
                'categories' => $categories,
            ]);
        }

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

        $categoryIds = $this->collectDescendantCategoryIds($category);

        $productQuery = Product::query()
            ->where('is_active', true)
            ->whereIn('category_id', $categoryIds)
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
