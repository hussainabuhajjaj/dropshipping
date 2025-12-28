<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront\Concerns;

use App\Domain\Products\Models\Category;
use Illuminate\Support\Facades\Cache;

trait FormatsCategories
{
    protected function categoryTree(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'children' => $category->children
                ->sortBy('name')
                ->map(fn (Category $child) => $this->categoryTree($child))
                ->values()
                ->all(),
        ];
    }

    protected function rootCategoriesTree(array $load = ['children']): array
    {
        $cacheKey = 'categories-tree:' . md5(json_encode($load));

        return Cache::remember($cacheKey, now()->addMinutes(20), function () use ($load) {
            $query = Category::query()
                ->whereNull('parent_id')
                ->orderBy('name');

            if (! empty($load)) {
                $query->with($load);
            }

            return $query->get()->map(fn (Category $category) => $this->categoryTree($category))->values()->all();
        });
    }
}
