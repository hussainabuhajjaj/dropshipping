<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Requests\Api\Mobile\V1\Category\CategoryShowRequest;
use App\Http\Resources\Mobile\V1\CategoryCardResource;
use App\Http\Resources\Mobile\V1\CategoryShowResource;
use App\Domain\Products\Models\Category as DomainCategory;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class CategoryController extends ApiController
{
    public function index(): JsonResponse
    {
        $locale = app()->getLocale();

        $categoryRows = Category::query()
            ->select(['id', 'parent_id'])
            ->where('is_active', true)
            ->get();

        $childrenByParentId = [];
        foreach ($categoryRows as $categoryRow) {
            $parentId = $categoryRow->parent_id ?? 0;
            $childrenByParentId[$parentId][] = (int) $categoryRow->id;
        }

        $directCounts = Product::query()
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        $subtreeCounts = [];
        $visiting = [];

        $countSubtree = function (int $categoryId) use (
            &$countSubtree,
            &$subtreeCounts,
            &$visiting,
            $childrenByParentId,
            $directCounts
        ): int {
            if (array_key_exists($categoryId, $subtreeCounts)) {
                return $subtreeCounts[$categoryId];
            }

            if (isset($visiting[$categoryId])) {
                return 0;
            }

            $visiting[$categoryId] = true;

            $count = (int) ($directCounts->get($categoryId, 0) ?? 0);
            foreach ($childrenByParentId[$categoryId] ?? [] as $childId) {
                $count += $countSubtree((int) $childId);
            }

            unset($visiting[$categoryId]);

            return $subtreeCounts[$categoryId] = $count;
        };

        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with([
                'translations' => fn ($q) => $q->where('locale', $locale),
                'children' => function ($query) use ($locale) {
                    $query
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->with(['translations' => fn ($q) => $q->where('locale', $locale)]);
                },
            ])
            ->orderBy('name')
            ->get();

        $categories = $categories
            ->map(function (DomainCategory $category) use ($countSubtree) {
                $category->setAttribute('product_count', $countSubtree((int) $category->id));

                if ($category->relationLoaded('children')) {
                    $children = $category->children
                        ->map(function (DomainCategory $child) use ($countSubtree) {
                            $child->setAttribute('product_count', $countSubtree((int) $child->id));

                            return $child;
                        })
                        ->filter(fn (DomainCategory $child) => (int) ($child->product_count ?? 0) > 0)
                        ->values();

                    $category->setRelation('children', $children);
                }

                return $category;
            })
            ->filter(fn (DomainCategory $category) => (int) ($category->product_count ?? 0) > 0)
            ->values();

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
