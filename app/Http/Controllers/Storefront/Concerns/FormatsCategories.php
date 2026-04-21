<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront\Concerns;

use App\Domain\Products\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

trait FormatsCategories
{
    protected function categoryTree(Category $category, bool $featuredOnly = false): array
    {
        $locale = app()->getLocale();
        $children = $category->children
            ->sortBy('name')
            ->map(fn (Category $child) => $this->categoryTree($child, $featuredOnly))
            ->filter()
            ->values()
            ->all();

        $hasProducts = (int) ($category->active_products_count ?? 0) > 0;

        // Keep featured categories even if they don't yet have direct products/children
        $isFeatured = (bool) ($category->is_featured ?? false);

        if ($featuredOnly && ! $isFeatured && empty($children)) {
            return [];
        }

        if (! $hasProducts && empty($children) && ! $isFeatured) {
            return [];
        }

        return [
            'id' => $category->id,
            'name' => $category->translatedValue('name', $locale),
            'slug' => $category->slug,
            'href' => '/categories/' . rawurlencode((string) $category->slug),
            'parent_id' => $category->parent_id,
            'is_featured' => (bool) $category->is_featured,
            'image' => $this->resolveCategoryImage($category->image ?? $category->hero_image ?? null),
            'hero_image' => $this->resolveCategoryImage($category->hero_image ?? $category->image ?? null),
            'product_count' => (int) ($category->active_products_count ?? 0),
            'children' => $children,
        ];
    }

    protected function rootCategoriesTree(array $load = ['children'], bool $featuredOnly = false): array
    {
        $locale = app()->getLocale();
        $version = \App\Domain\Products\Models\Category::query()->max('updated_at');
        $cacheKey = 'categories-tree:active-products:' . md5(json_encode([$load, $locale, $version, $featuredOnly]));

        try {
            return Cache::remember($cacheKey, now()->addMinutes(20), function () use ($load, $locale, $featuredOnly) {
                $query = Category::query()
                    ->where('is_active', true)
                    ->whereNull('parent_id')
                    ->orderBy('name')
                    ->withCount(['products as active_products_count' => fn ($q) => $q->where('is_active', true)])
                    ->with(['translations' => fn ($q) => $q->where('locale', $locale)]);

                $loadChildren = in_array('children', $load, true) || in_array('children.children', $load, true);
                $loadGrandchildren = in_array('children.children', $load, true);

                if ($loadChildren) {
                    $query->with(['children' => function ($childQuery) use ($loadGrandchildren, $locale) {
                        $childQuery
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->withCount(['products as active_products_count' => fn ($q) => $q->where('is_active', true)])
                            ->with(['translations' => fn ($q) => $q->where('locale', $locale)]);

                        if ($loadGrandchildren) {
                            $childQuery->with(['children' => function ($grandQuery) use ($locale) {
                                $grandQuery
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->withCount(['products as active_products_count' => fn ($q) => $q->where('is_active', true)])
                                    ->with(['translations' => fn ($q) => $q->where('locale', $locale)]);
                            }]);
                        }
                    }]);
                }

                return $query->get()
                    ->map(fn (Category $category) => $this->categoryTree($category, $featuredOnly))
                    ->filter()
                    ->values()
                    ->all();
            });
        } catch (QueryException $e) {
            // If the products table is unhealthy (e.g. corrupted index), do not take down the entire storefront.
            report($e);
            return [];
        } catch (Throwable $e) {
            report($e);
            return [];
        }
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
