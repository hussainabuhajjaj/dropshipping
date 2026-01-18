<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront\Concerns;

use App\Domain\Products\Models\Category;
use Illuminate\Support\Facades\Cache;

trait FormatsCategories
{
    protected function categoryTree(Category $category): array
    {
        $children = $category->children
            ->sortBy('name')
            ->map(fn (Category $child) => $this->categoryTree($child))
            ->filter()
            ->values()
            ->all();

        $hasProducts = (int) ($category->active_products_count ?? 0) > 0;

        if (! $hasProducts && empty($children)) {
            return [];
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'children' => $children,
        ];
    }

    protected function rootCategoriesTree(array $load = ['children']): array
    {
        $cacheKey = 'categories-tree:active-products:' . md5(json_encode($load));

        return Cache::remember($cacheKey, now()->addMinutes(20), function () use ($load) {
            $query = Category::query()
                ->whereNull('parent_id')
                ->orderBy('name')
                ->withCount(['products as active_products_count' => fn ($q) => $q->where('is_active', true)]);

            $loadChildren = in_array('children', $load, true) || in_array('children.children', $load, true);
            $loadGrandchildren = in_array('children.children', $load, true);

            if ($loadChildren) {
                $query->with(['children' => function ($childQuery) use ($loadGrandchildren) {
                    $childQuery
                        ->orderBy('name')
                        ->withCount(['products as active_products_count' => fn ($q) => $q->where('is_active', true)]);

                    if ($loadGrandchildren) {
                        $childQuery->with(['children' => function ($grandQuery) {
                            $grandQuery
                                ->orderBy('name')
                                ->withCount(['products as active_products_count' => fn ($q) => $q->where('is_active', true)]);
                        }]);
                    }
                }]);
            }

            return $query->get()
                ->map(fn (Category $category) => $this->categoryTree($category))
                ->filter()
                ->values()
                ->all();
        });
    }
}
