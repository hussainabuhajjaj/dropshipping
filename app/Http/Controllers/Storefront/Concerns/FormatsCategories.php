<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront\Concerns;

use App\Domain\Products\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

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

        // Keep featured categories even if they don't yet have direct products/children
        $isFeatured = (bool) ($category->is_featured ?? false);

        if (! $hasProducts && empty($children) && ! $isFeatured) {
            return [];
        }

        return [
            'id' => $category->id,
            'name' => $category->translatedValue('name', $locale),
            'slug' => $category->slug,
            'is_featured' => (bool) $category->is_featured,
            'image' => $this->resolveCategoryImage($category->hero_image ?? null),
            'children' => $children,
        ];
    }

    protected function rootCategoriesTree(array $load = ['children']): array
    {
        $locale = app()->getLocale();
        $version = \App\Domain\Products\Models\Category::max('updated_at');
        $cacheKey = 'categories-tree:active-products:' . md5(json_encode([$load, $locale, $version]));

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

    protected function resolveCategoryImage(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url(Storage::url($path));
    }
}
