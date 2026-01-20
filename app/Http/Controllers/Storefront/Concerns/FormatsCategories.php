<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront\Concerns;

use App\Domain\Products\Models\Category;
use Illuminate\Support\Facades\Cache;

trait FormatsCategories
{
    protected function categoryTree(Category $category): array
    {
        $locale = app()->getLocale();
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
            'name' => $category->translatedValue('name', $locale),
            'slug' => $category->slug,
            'children' => $children,
        ];
    }

    protected function rootCategoriesTree(array $load = ['children']): array
    {
        $locale = app()->getLocale();
        $cacheKey = 'categories-tree:active-products:' . md5(json_encode([$load, $locale]));

        return Cache::remember($cacheKey, now()->addMinutes(20), function () use ($load, $locale) {
            $query = Category::query()
                ->whereNull('parent_id')
                ->orderBy('name')
                ->withCount(['products as active_products_count' => fn ($q) => $q->where('is_active', true)])
                ->with(['translations' => fn ($q) => $q->where('locale', $locale)]);

            $loadChildren = in_array('children', $load, true) || in_array('children.children', $load, true);
            $loadGrandchildren = in_array('children.children', $load, true);

            if ($loadChildren) {
                $query->with(['children' => function ($childQuery) use ($loadGrandchildren, $locale) {
                    $childQuery
                        ->orderBy('name')
                        ->withCount(['products as active_products_count' => fn ($q) => $q->where('is_active', true)])
                        ->with(['translations' => fn ($q) => $q->where('locale', $locale)]);

                    if ($loadGrandchildren) {
                        $childQuery->with(['children' => function ($grandQuery) use ($locale) {
                            $grandQuery
                                ->orderBy('name')
                                ->withCount(['products as active_products_count' => fn ($q) => $q->where('is_active', true)])
                                ->with(['translations' => fn ($q) => $q->where('locale', $locale)]);
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
