<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\StorefrontCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class MobileCollectionService
{
    public function paginateCollection(
        StorefrontCollection $collection,
        ?string $locale,
        array $filters,
        int $perPage,
        int $page
    ): LengthAwarePaginator {
        $mode = (string) ($collection->selection_mode ?: 'rules');
        $rules = is_array($collection->rules) ? $collection->rules : [];
        $manualIds = $collection->manualProductIds();
        $limit = $this->normalizeLimit($collection->product_limit ?? Arr::get($rules, 'limit'));

        if ($this->shouldFallbackToManualProducts($mode, $rules, $manualIds)) {
            return $this->paginateQuery(
                $this->buildManualQuery($manualIds, $filters, $locale),
                $perPage,
                $page,
                $limit
            );
        }

        return match ($mode) {
            'manual' => $this->paginateQuery(
                $this->buildManualQuery($manualIds, $filters, $locale),
                $perPage,
                $page,
                $limit
            ),
            'hybrid' => $this->paginateHybridCollection($rules, $manualIds, $filters, $locale, $perPage, $page, $limit),
            default => $this->paginateQuery(
                $this->buildRuleQuery($rules, $manualIds, $filters, $locale),
                $perPage,
                $page,
                $limit
            ),
        };
    }

    public function paginateLegacyCategory(
        Category $category,
        ?string $locale,
        array $filters,
        int $perPage,
        int $page
    ): LengthAwarePaginator {
        $query = $this->baseProductQuery($locale)
            ->where('is_active', true)
            ->whereIn('category_id', $this->descendantCategoryIds([(int) $category->id]));

        $this->applyFilters($query, $filters);
        $this->applySort($query, Arr::get($filters, 'sort'));

        return $this->paginateQuery($query, $perPage, $page);
    }

    private function paginateHybridCollection(
        array $rules,
        array $manualIds,
        array $filters,
        ?string $locale,
        int $perPage,
        int $page,
        ?int $limit = null
    ): LengthAwarePaginator {
        if ($manualIds === []) {
            return $this->paginateQuery(
                $this->buildRuleQuery($rules, [], $filters, $locale),
                $perPage,
                $page,
                $limit
            );
        }

        $manualCountQuery = $this->buildManualQuery($manualIds, $filters, $locale, false);
        $ruleCountQuery = $this->buildRuleQuery($rules, $manualIds, $filters, $locale, false);

        $manualTotal = $manualCountQuery ? (clone $manualCountQuery)->count() : 0;
        $ruleTotal = (clone $ruleCountQuery)->count();
        $total = $manualTotal + $ruleTotal;

        if ($limit !== null) {
            $total = min($total, $limit);
        }

        $offset = max(0, ($page - 1) * $perPage);
        if ($offset >= $total) {
            return $this->makePaginator(collect(), $perPage, $page, $total);
        }

        $candidateLimit = min($total, $offset + $perPage);
        $manualItems = collect();
        if ($candidateLimit > 0) {
            $manualQuery = $this->buildManualQuery($manualIds, $filters, $locale);
            if ($manualQuery) {
                $manualItems = $manualQuery->limit($candidateLimit)->get();
            }
        }

        $ruleItems = collect();
        if ($candidateLimit > 0) {
            $ruleItems = $this->buildRuleQuery($rules, $manualIds, $filters, $locale)
                ->limit($candidateLimit)
                ->get();
        }

        $items = $this->sortResolvedProducts(
            $manualItems->concat($ruleItems)->values(),
            Arr::get($filters, 'sort')
        )->slice($offset, $perPage)->values();

        return $this->makePaginator($items, $perPage, $page, $total);
    }

    private function buildRuleQuery(
        array $rules,
        array $excludeIds,
        array $filters,
        ?string $locale,
        bool $withRelations = true
    ): Builder {
        $query = $this->baseProductQuery($locale, $withRelations);

        $isActive = Arr::get($rules, 'is_active', true);
        if ($isActive !== null) {
            $query->where('is_active', (bool) $isActive);
        }

        $categoryIds = Arr::get($rules, 'category_ids');
        if (is_array($categoryIds) && $categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        $categorySlugs = Arr::get($rules, 'category_slugs');
        if (is_array($categorySlugs) && $categorySlugs !== []) {
            $rootIds = Category::query()
                ->whereIn('slug', $categorySlugs)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $treeIds = $this->descendantCategoryIds($rootIds);
            if ($treeIds !== []) {
                $query->whereIn('category_id', $treeIds);
            }
        }

        $excludeCategorySlugs = Arr::get($rules, 'exclude_category_slugs');
        if (is_array($excludeCategorySlugs) && $excludeCategorySlugs !== []) {
            $excludeRootIds = Category::query()
                ->whereIn('slug', $excludeCategorySlugs)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $excludeTreeIds = $this->descendantCategoryIds($excludeRootIds);
            if ($excludeTreeIds !== []) {
                $query->whereNotIn('category_id', $excludeTreeIds);
            }
        }

        $query->priceRange(
            $this->normalizeNumeric(Arr::get($rules, 'min_price')),
            $this->normalizeNumeric(Arr::get($rules, 'max_price'))
        );

        if (Arr::get($rules, 'in_stock')) {
            $query->where('stock_on_hand', '>', 0);
        }

        $isFeatured = Arr::get($rules, 'is_featured');
        if ($isFeatured !== null && $isFeatured !== '') {
            $query->where('is_featured', filter_var($isFeatured, FILTER_VALIDATE_BOOLEAN));
        }

        $minRating = Arr::get($rules, 'min_rating');
        if ($minRating !== null && is_numeric($minRating)) {
            $query->having('reviews_avg_rating', '>=', (float) $minRating);
        }

        $search = trim((string) Arr::get($rules, 'query', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $excludeTerms = Arr::get($rules, 'exclude_terms');
        if (is_array($excludeTerms) && $excludeTerms !== []) {
            $terms = collect($excludeTerms)
                ->map(fn ($term) => trim((string) $term))
                ->filter()
                ->values();

            if ($terms->isNotEmpty()) {
                $query->where(function (Builder $builder) use ($terms): void {
                    foreach ($terms as $term) {
                        $builder
                            ->where('name', 'not like', '%' . $term . '%')
                            ->where('description', 'not like', '%' . $term . '%');
                    }
                });
            }
        }

        $includeIds = Arr::get($rules, 'include_product_ids');
        if (is_array($includeIds) && $includeIds !== []) {
            $query->whereIn('id', $includeIds);
        }

        $excludeIds = array_values(array_unique(array_merge($excludeIds, Arr::get($rules, 'exclude_product_ids', []))));
        if ($excludeIds !== []) {
            $query->whereNotIn('id', $excludeIds);
        }

        $this->applyFilters($query, $filters);
        $this->applySort($query, Arr::get($filters, 'sort') ?: Arr::get($rules, 'sort'));

        return $query;
    }

    private function buildManualQuery(
        array $ids,
        array $filters,
        ?string $locale,
        bool $withRelations = true
    ): ?Builder {
        if ($ids === []) {
            return null;
        }

        $query = $this->baseProductQuery($locale, $withRelations)
            ->whereIn('id', $ids);

        $this->applyFilters($query, $filters);

        $sort = Arr::get($filters, 'sort');
        if ($sort) {
            $this->applySort($query, $sort);
            return $query;
        }

        $caseSql = 'CASE id ' . collect($ids)
            ->values()
            ->map(fn ($id, $index) => 'WHEN ' . (int) $id . ' THEN ' . $index)
            ->implode(' ') . ' ELSE ' . count($ids) . ' END';

        return $query->orderByRaw($caseSql);
    }

    private function baseProductQuery(?string $locale, bool $withRelations = true): Builder
    {
        $query = Product::query();

        if (! $withRelations) {
            return $query;
        }

        return $query
            ->with($this->productRelations($locale))
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');
    }

    private function productRelations(?string $locale): array
    {
        return [
            'images' => fn ($query) => $query
                ->select(['id', 'product_id', 'url', 'position'])
                ->orderBy('position'),
            'category' => fn ($query) => $query
                ->select(['id', 'name', 'slug', 'parent_id'])
                ->with([
                    'translations' => fn ($translationQuery) => $translationQuery
                        ->select(['id', 'category_id', 'locale', 'name'])
                        ->when($locale, fn ($q) => $q->where('locale', $locale)),
                ]),
            'variants' => fn ($query) => $query->select([
                'id',
                'product_id',
                'title',
                'price',
                'compare_at_price',
                'currency',
                'sku',
                'cj_vid',
                'stock_on_hand',
                'low_stock_threshold',
                'options',
                'metadata',
                'variant_image',
            ]),
            'translations' => fn ($query) => $query
                ->select(['id', 'product_id', 'locale', 'name', 'description'])
                ->when($locale, fn ($q) => $q->where('locale', $locale)),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query->priceRange(
            $this->normalizeNumeric(Arr::get($filters, 'min_price')),
            $this->normalizeNumeric(Arr::get($filters, 'max_price'))
        );

        $inStock = Arr::get($filters, 'in_stock');
        if ($inStock === true || $inStock === 1 || $inStock === '1' || $inStock === 'true') {
            $query->where('stock_on_hand', '>', 0);
        }

        $brand = trim((string) Arr::get($filters, 'brand', ''));
        if ($brand !== '') {
            $query->where(function (Builder $builder) use ($brand): void {
                $builder
                    ->where('attributes->brand', $brand)
                    ->orWhereJsonContains('attributes->brand', $brand);
            });
        }

        $attributes = Arr::get($filters, 'attributes', []);
        if (is_array($attributes)) {
            foreach ($attributes as $key => $value) {
                if (! is_string($key)) {
                    continue;
                }

                if (is_array($value)) {
                    $value = collect($value)->first(fn ($item) => is_scalar($item) && trim((string) $item) !== '');
                }

                $normalizedValue = trim((string) $value);
                if ($normalizedValue === '') {
                    continue;
                }

                $query->where(function (Builder $builder) use ($key, $normalizedValue): void {
                    $builder
                        ->where('attributes->' . $key, $normalizedValue)
                        ->orWhereJsonContains('attributes->' . $key, $normalizedValue);
                });
            }
        }
    }

    private function applySort(Builder $query, ?string $sort): void
    {
        $sort = is_string($sort) ? trim($sort) : null;

        $query->reorder();

        match ($sort) {
            'price_asc' => $query
                ->withMin('variants', 'price')
                ->orderByRaw('COALESCE(variants_min_price, selling_price) asc'),
            'price_desc' => $query
                ->withMin('variants', 'price')
                ->orderByRaw('COALESCE(variants_min_price, selling_price) desc'),
            'rating' => $query->orderByDesc('reviews_avg_rating'),
            'popularity', 'popular' => $query->orderByDesc('reviews_count'),
            'featured' => $query->orderByDesc('is_featured')->orderByDesc('created_at'),
            'random' => $query->inRandomOrder(),
            default => $query->latest(),
        };
    }

    private function sortResolvedProducts(Collection $products, ?string $sort): Collection
    {
        $sort = is_string($sort) ? trim($sort) : null;

        $priceOf = function ($product): float {
            $variantPrices = collect($product->variants ?? [])
                ->pluck('price')
                ->filter(fn ($price) => is_numeric($price))
                ->map(fn ($price) => (float) $price);

            $variantMin = $variantPrices->min();
            $sellingPrice = is_numeric($product->selling_price ?? null) ? (float) $product->selling_price : null;

            if ($variantMin !== null && $sellingPrice !== null) {
                return min($variantMin, $sellingPrice);
            }

            return $variantMin ?? $sellingPrice ?? 0.0;
        };

        return match ($sort) {
            'price_asc' => $products->sortBy($priceOf)->values(),
            'price_desc' => $products->sortByDesc($priceOf)->values(),
            'rating' => $products->sortByDesc(fn ($product) => (float) ($product->reviews_avg_rating ?? 0))->values(),
            'popularity', 'popular' => $products->sortByDesc(fn ($product) => (int) ($product->reviews_count ?? 0))->values(),
            'featured' => $products->sortByDesc(fn ($product) => [
                (int) ($product->is_featured ?? false),
                optional($product->created_at)?->getTimestamp() ?? 0,
            ])->values(),
            default => $products->sortByDesc(fn ($product) => optional($product->created_at)?->getTimestamp() ?? 0)->values(),
        };
    }

    private function paginateQuery(?Builder $query, int $perPage, int $page, ?int $limit = null): LengthAwarePaginator
    {
        if (! $query) {
            return $this->makePaginator(collect(), $perPage, $page, 0);
        }

        $total = (clone $query)->count();
        if ($limit !== null) {
            $total = min($total, $limit);
        }

        $offset = max(0, ($page - 1) * $perPage);
        if ($offset >= $total) {
            return $this->makePaginator(collect(), $perPage, $page, $total);
        }

        $remaining = $total - $offset;
        $items = $query
            ->offset($offset)
            ->limit(min($perPage, $remaining))
            ->get();

        return $this->makePaginator($items, $perPage, $page, $total);
    }

    private function makePaginator(Collection $items, int $perPage, int $page, int $total): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $items->values(),
            $total,
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * @param array<int, int> $rootIds
     * @return array<int, int>
     */
    private function descendantCategoryIds(array $rootIds): array
    {
        $rootIds = collect($rootIds)->map(fn ($id) => (int) $id)->filter()->values()->all();
        if ($rootIds === []) {
            return [];
        }

        $all = $rootIds;
        $frontier = $rootIds;

        for ($i = 0; $i < 12; $i++) {
            $children = Category::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $children = array_values(array_diff($children, $all));
            if ($children === []) {
                break;
            }

            $all = array_merge($all, $children);
            $frontier = $children;
        }

        return array_values(array_unique($all));
    }

    private function normalizeLimit(mixed $limit): ?int
    {
        if ($limit === null || $limit === '') {
            return null;
        }

        $normalized = (int) $limit;

        return $normalized > 0 ? $normalized : null;
    }

    private function normalizeNumeric(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function shouldFallbackToManualProducts(string $mode, array $rules, array $manualIds): bool
    {
        if ($mode !== 'rules' || $manualIds === []) {
            return false;
        }

        foreach ($rules as $value) {
            if (is_array($value) && $value !== []) {
                return false;
            }

            if (! is_array($value) && $value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }
}
