<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as SupportCollection;
use App\Services\Storefront\ProductMetaExtractor;

class StorefrontCollection extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'type',
        'description',
        'hero_kicker',
        'hero_subtitle',
        'hero_image',
        'hero_cta_label',
        'hero_cta_url',
        'content',
        'seo_title',
        'seo_description',
        'is_active',
        'starts_at',
        'ends_at',
        'timezone',
        'locale_visibility',
        'locale_overrides',
        'selection_mode',
        'rules',
        'product_limit',
        'sort_by',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'locale_visibility' => 'array',
        'locale_overrides' => 'array',
        'rules' => 'array',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'storefront_collection_products', 'storefront_collection_id', 'product_id')
            ->withPivot(['position'])
            ->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function localeOverrideMap(): array
    {
        $overrides = $this->locale_overrides ?? [];
        return collect($overrides)
            ->filter(fn ($row) => is_array($row) && ! empty($row['locale']))
            ->keyBy('locale')
            ->all();
    }

    public function localizedValue(string $field, ?string $locale): ?string
    {
        if (! $locale) {
            return $this->{$field} ?? null;
        }

        $override = $this->localeOverrideMap()[$locale] ?? null;
        if ($override && array_key_exists($field, $override) && $override[$field] !== null && $override[$field] !== '') {
            return (string) $override[$field];
        }

        return $this->{$field} ?? null;
    }

    public function isVisibleForLocale(?string $locale): bool
    {
        $allowed = $this->locale_visibility ?? [];
        if (! $allowed || ! is_array($allowed) || count($allowed) === 0) {
            return true;
        }
        if (! $locale) {
            return false;
        }
        return in_array($locale, $allowed, true);
    }

    public function resolveScheduleForLocale(?string $locale): array
    {
        $schedule = [
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'timezone' => $this->timezone,
        ];

        if (! $locale) {
            return $schedule;
        }

        $override = $this->localeOverrideMap()[$locale] ?? null;
        if (! $override || ! is_array($override)) {
            return $schedule;
        }

        return [
            'starts_at' => $override['starts_at'] ?? $schedule['starts_at'],
            'ends_at' => $override['ends_at'] ?? $schedule['ends_at'],
            'timezone' => $override['timezone'] ?? $schedule['timezone'],
        ];
    }

    public function isActiveForLocale(?string $locale, ?Carbon $now = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->isVisibleForLocale($locale)) {
            return false;
        }

        $now = $now ?: now();
        $schedule = $this->resolveScheduleForLocale($locale);
        $timezone = $schedule['timezone'] ?: config('app.timezone');
        $now = $now->copy()->timezone($timezone);

        if ($schedule['starts_at']) {
            $start = $this->parseScheduleDate($schedule['starts_at'], $timezone);
            if ($start && $now->lt($start)) {
                return false;
            }
        }

        if ($schedule['ends_at']) {
            $end = $this->parseScheduleDate($schedule['ends_at'], $timezone);
            if ($end && $now->gt($end)) {
                return false;
            }
        }

        return true;
    }

    public function manualProductIds(): array
    {
        if (! $this->exists) {
            return [];
        }

        return $this->products()
            ->orderBy('storefront_collection_products.position')
            ->pluck('products.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function resolveProducts(?string $locale = null, ?int $limit = null)
    {
        $mode = $this->selection_mode ?: 'rules';
        $rules = $this->rules ?? [];
        $limit = $limit ?? $this->product_limit ?? Arr::get($rules, 'limit');

        $manualIds = $this->manualProductIds();
        $manualProducts = collect();
        if (! empty($manualIds)) {
            $manualProducts = $this->loadProductsByIds($manualIds, $locale);
        }

        if ($this->shouldFallbackToManualProducts($mode, $rules, $manualIds)) {
            return $this->sliceToLimit($manualProducts, $limit);
        }

        if ($mode === 'manual') {
            return $this->sliceToLimit($manualProducts, $limit);
        }

        $ruleProducts = $this->loadRuleProducts($rules, $manualIds, $limit, $locale);

        if ($mode === 'rules') {
            return $ruleProducts;
        }

        // Hybrid: manual first, then fill with rules
        $combined = $manualProducts->concat($ruleProducts)->values();
        return $this->sliceToLimit($combined, $limit);
    }

    public function paginateResolvedProducts(?string $locale = null, int $perPage = 18, ?int $page = null): LengthAwarePaginator
    {
        $mode = $this->selection_mode ?: 'rules';
        $rules = $this->rules ?? [];
        $limit = $this->normalizeLimit($this->product_limit ?? Arr::get($rules, 'limit'));
        $page = max(1, $page ?? LengthAwarePaginator::resolveCurrentPage());

        $manualIds = $this->manualProductIds();

        if ($this->shouldFallbackToManualProducts($mode, $rules, $manualIds)) {
            return $this->paginateManualProducts($manualIds, $perPage, $page, $limit, $locale);
        }

        if ($mode === 'rules') {
            return $this->paginateRuleProducts($rules, $manualIds, $locale, $perPage, $page, $limit);
        }

        if ($mode === 'manual') {
            return $this->paginateManualProducts($manualIds, $perPage, $page, $limit, $locale);
        }

        return $this->paginateHybridProducts($rules, $manualIds, $locale, $perPage, $page, $limit);
    }

    public function paginateFilteredProducts(
        array $filters = [],
        ?string $locale = null,
        int $perPage = 18,
        ?int $page = null
    ): LengthAwarePaginator {
        $page = max(1, $page ?? LengthAwarePaginator::resolveCurrentPage());
        $mode = $this->selection_mode ?: 'rules';
        $rules = $this->rules ?? [];
        $limit = $this->normalizeLimit($this->product_limit ?? Arr::get($rules, 'limit'));
        $manualIds = $this->manualProductIds();

        if ($this->shouldFallbackToManualProducts($mode, $rules, $manualIds)) {
            return $this->paginateQueryResults(
                $this->buildManualQuery($manualIds, $filters, true, $locale),
                $perPage,
                $page,
                $limit
            );
        }

        if ($mode === 'manual') {
            return $this->paginateQueryResults(
                $this->buildManualQuery($manualIds, $filters, true, $locale),
                $perPage,
                $page,
                $limit
            );
        }

        if ($mode === 'rules') {
            return $this->paginateQueryResults(
                $this->buildRuleQuery($rules, $manualIds, $locale, $filters),
                $perPage,
                $page,
                $limit
            );
        }

        if ($mode === 'hybrid' && $manualIds !== []) {
            return $this->paginateHybridFilteredProducts($rules, $manualIds, $locale, $filters, $perPage, $page, $limit);
        }

        $query = $this->buildRuleQuery($rules, [], $locale, $filters);

        if ($query) {
            return $this->paginateQueryResults($query, $perPage, $page, $limit);
        }

        $resolved = $this->applyFiltersToResolvedProducts($this->resolveProducts($locale), $filters);
        if ($limit !== null) {
            $resolved = $resolved->take($limit)->values();
        }

        $total = $resolved->count();
        $offset = max(0, ($page - 1) * $perPage);
        $items = $resolved->slice($offset, $perPage)->values();

        return $this->paginateCollection($items, $perPage, $page, $total);
    }

    public function availableFilters(?string $locale = null): array
    {
        $query = $this->filteredQuery([], $locale, false);

        if ($query) {
            $metaQuery = (clone $query)
                ->setEagerLoads([])
                ->reorder();

            $aggregate = (clone $metaQuery)
                ->selectRaw('MIN(selling_price) as min_price, MAX(selling_price) as max_price')
                ->first();

            $priceRange = [
                'min' => is_numeric($aggregate?->min_price) ? round((float) $aggregate->min_price, 2) : null,
                'max' => is_numeric($aggregate?->max_price) ? round((float) $aggregate->max_price, 2) : null,
            ];

            $total = (clone $metaQuery)->count();

            if ($total > 5000) {
                return [
                    'price_range' => $priceRange,
                    'attributeDefs' => [],
                    'brands' => null,
                ];
            }

            return [
                'price_range' => $priceRange,
                ...app(ProductMetaExtractor::class)->extractFromQuery($metaQuery),
            ];
        }

        $rules = $this->rules ?? [];
        $query = $this->buildRuleQuery($rules, [], $locale, [], false);

        if ($query) {
            $metaQuery = (clone $query)
                ->setEagerLoads([])
                ->reorder();

            $aggregate = (clone $metaQuery)
                ->selectRaw('MIN(selling_price) as min_price, MAX(selling_price) as max_price')
                ->first();

            $priceRange = [
                'min' => is_numeric($aggregate?->min_price) ? round((float) $aggregate->min_price, 2) : null,
                'max' => is_numeric($aggregate?->max_price) ? round((float) $aggregate->max_price, 2) : null,
            ];

            $total = (clone $metaQuery)->count();

            if ($total > 5000) {
                return [
                    'price_range' => $priceRange,
                    'attributeDefs' => [],
                    'brands' => null,
                ];
            }

            return [
                'price_range' => $priceRange,
                ...app(ProductMetaExtractor::class)->extractFromQuery($metaQuery),
            ];
        }

        return [
            'price_range' => ['min' => null, 'max' => null],
            'attributeDefs' => [],
            'brands' => null,
        ];
    }

    private function loadRuleProducts(array $rules, array $excludeIds, ?int $limit, ?string $locale)
    {
        $query = $this->buildRuleQuery($rules, $excludeIds, $locale);
        if ($limit) {
            $query->limit($limit);
        }
        return $query->get();
    }

    private function loadProductsByIds(array $ids, ?string $locale = null)
    {
        if (empty($ids)) {
            return collect();
        }

        if (! $this->exists) {
            return collect();
        }

        $positionMap = $this->products()
            ->whereIn('products.id', $ids)
            ->pluck('storefront_collection_products.position', 'products.id')
            ->map(fn ($pos) => (int) $pos)
            ->all();

        $products = Product::query()
            ->whereIn('id', $ids)
            ->with($this->productRelations($locale))
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->get();

        return $products->sortBy(function ($product) use ($positionMap) {
            return $positionMap[$product->id] ?? 9999;
        })->values();
    }

    private function buildRuleQuery(
        array $rules,
        array $excludeIds,
        ?string $locale,
        array $filters = [],
        bool $withRelations = true
    ): Builder
    {
        $query = $this->baseProductQuery($locale, $withRelations);

        $isActive = Arr::get($rules, 'is_active', true);
        if ($isActive !== null) {
            $query->where('is_active', (bool) $isActive);
        }

        // Category scoping (supports both exact category_id lists and subtree filtering by slug).
        $categoryIds = Arr::get($rules, 'category_ids');
        if (is_array($categoryIds) && count($categoryIds) > 0) {
            $query->whereIn('category_id', $categoryIds);
        }

        $categorySlugs = Arr::get($rules, 'category_slugs');
        if (is_array($categorySlugs) && count($categorySlugs) > 0) {
            // Treat category_slugs as "root categories" and include their descendants. This prevents
            // "mixed collections" caused by relying on keyword search alone, and matches how users
            // expect category-based collections to behave.
            $rootIds = Category::query()
                ->whereIn('slug', $categorySlugs)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $treeIds = $this->descendantCategoryIds($rootIds);
            if ($treeIds !== []) {
                $query->whereIn('category_id', $treeIds);
            } else {
                // Fallback to the old behavior if slugs didn't match.
                $query->whereHas('category', function (Builder $builder) use ($categorySlugs) {
                    $builder->whereIn('slug', $categorySlugs);
                });
            }
        }

        $excludeCategorySlugs = Arr::get($rules, 'exclude_category_slugs');
        if (is_array($excludeCategorySlugs) && count($excludeCategorySlugs) > 0) {
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

        $minPrice = Arr::get($rules, 'min_price');
        if ($minPrice !== null && is_numeric($minPrice)) {
            $query->where('selling_price', '>=', (float) $minPrice);
        }

        $maxPrice = Arr::get($rules, 'max_price');
        if ($maxPrice !== null && is_numeric($maxPrice)) {
            $query->where('selling_price', '<=', (float) $maxPrice);
        }

        $inStock = Arr::get($rules, 'in_stock');
        if ($inStock) {
            $query->where('stock_on_hand', '>', 0);
        }

        $isFeatured = Arr::get($rules, 'is_featured');
        if ($isFeatured !== null && $isFeatured !== '') {
            $query->where('is_featured', filter_var($isFeatured, FILTER_VALIDATE_BOOLEAN));
        }

        $minRating = Arr::get($rules, 'min_rating');
        if ($minRating !== null && is_numeric($minRating) && $withRelations) {
            $query->having('reviews_avg_rating', '>=', (float) $minRating);
        }

        $search = Arr::get($rules, 'query');
        if ($search) {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhereHas('category', function (Builder $categoryBuilder) use ($search) {
                        $categoryBuilder->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        $excludeTerms = Arr::get($rules, 'exclude_terms');
        if (is_array($excludeTerms) && count($excludeTerms) > 0) {
            $terms = collect($excludeTerms)
                ->map(fn ($t) => trim((string) $t))
                ->filter()
                ->take(20)
                ->values()
                ->all();

            if ($terms !== []) {
                $query->where(function (Builder $builder) use ($terms) {
                    foreach ($terms as $term) {
                        $builder->where('name', 'not like', '%' . $term . '%')
                            ->where('description', 'not like', '%' . $term . '%');
                    }
                });
            }
        }

        $includeIds = Arr::get($rules, 'include_product_ids');
        if (is_array($includeIds) && count($includeIds) > 0) {
            $query->whereIn('id', $includeIds);
        }

        $excludeIds = array_merge($excludeIds, Arr::get($rules, 'exclude_product_ids', []));
        if (! empty($excludeIds)) {
            $query->whereNotIn('id', array_unique($excludeIds));
        }

        $query = $this->applyRuntimeFilters($query, $filters);
        $this->applySort($query, Arr::get($filters, 'sort') ?: Arr::get($rules, 'sort') ?: $this->sort_by);

        return $query;
    }

    private function filteredQuery(array $filters, ?string $locale, bool $withRelations = true): ?Builder
    {
        $mode = $this->selection_mode ?: 'rules';
        $rules = $this->rules ?? [];
        $manualIds = $this->manualProductIds();

        if ($this->shouldFallbackToManualProducts($mode, $rules, $manualIds)) {
            return $this->buildManualQuery($manualIds, $filters, $withRelations, $locale);
        }

        if ($mode === 'manual') {
            return $this->buildManualQuery($manualIds, $filters, $withRelations, $locale);
        }

        if ($mode === 'rules') {
            return $this->buildRuleQuery($rules, $manualIds, $locale, $filters, $withRelations);
        }

        if ($mode === 'hybrid' && $manualIds === []) {
            return $this->buildRuleQuery($rules, [], $locale, $filters, $withRelations);
        }

        return null;
    }

    private function buildManualQuery(array $ids, array $filters, bool $withRelations = true, ?string $locale = null): ?Builder
    {
        if ($ids === []) {
            return null;
        }

        $query = $this->baseProductQuery($locale, $withRelations)
            ->whereIn('id', $ids);

        $query = $this->applyRuntimeFilters($query, $filters);

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

    private function buildHybridManualQuery(array $ids, array $filters, bool $withRelations = true, ?string $locale = null): ?Builder
    {
        if ($ids === []) {
            return null;
        }

        $query = $this->baseProductQuery($locale, $withRelations)
            ->whereIn('id', $ids);

        $query = $this->applyRuntimeFilters($query, $filters);
        $this->applySort($query, Arr::get($filters, 'sort'));

        return $query;
    }

    private function baseProductQuery(?string $locale = null, bool $withRelations = true): Builder
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

    private function productRelations(?string $locale = null): array
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

    private function applyRuntimeFilters(Builder $query, array $filters): Builder
    {
        $minPrice = Arr::get($filters, 'min_price');
        if ($minPrice !== null && is_numeric($minPrice)) {
            $query->where('selling_price', '>=', (float) $minPrice);
        }

        $maxPrice = Arr::get($filters, 'max_price');
        if ($maxPrice !== null && is_numeric($maxPrice)) {
            $query->where('selling_price', '<=', (float) $maxPrice);
        }

        $inStock = Arr::get($filters, 'in_stock');
        if ($inStock === true || $inStock === 1 || $inStock === '1' || $inStock === 'true') {
            $query->where('stock_on_hand', '>', 0);
        }

        $minRating = Arr::get($filters, 'rating');
        if ($minRating !== null && is_numeric($minRating)) {
            $query->having('reviews_avg_rating', '>=', (float) $minRating);
        }

        $brand = trim((string) Arr::get($filters, 'brand', ''));
        if ($brand !== '') {
            $query->where(function (Builder $builder) use ($brand) {
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

                $query->where(function (Builder $builder) use ($key, $normalizedValue) {
                    $builder
                        ->where('attributes->' . $key, $normalizedValue)
                        ->orWhereJsonContains('attributes->' . $key, $normalizedValue);
                });
            }
        }

        return $query;
    }

    private function applySort(Builder $query, ?string $sort): void
    {
        $sortable = [
            'price_asc' => ['selling_price', 'asc'],
            'price_desc' => ['selling_price', 'desc'],
            'newest' => ['created_at', 'desc'],
            'rating' => ['reviews_avg_rating', 'desc'],
            'popularity' => ['reviews_count', 'desc'],
            'popular' => ['reviews_count', 'desc'],
            'featured' => ['is_featured', 'desc'],
        ];

        $sort = is_string($sort) ? trim($sort) : null;

        $query->reorder();

        if ($sort === 'random') {
            $query->inRandomOrder();
            return;
        }

        if ($sort && isset($sortable[$sort])) {
            [$field, $direction] = $sortable[$sort];
            $query->orderBy($field, $direction);
            if ($sort === 'featured') {
                $query->orderBy('created_at', 'desc');
            }
            return;
        }

        $query->orderBy('created_at', 'desc');
    }

    private function applyFiltersToResolvedProducts(SupportCollection $products, array $filters): SupportCollection
    {
        $filtered = $products->filter(function ($product) use ($filters): bool {
            $price = (float) ($product->selling_price ?? 0);
            $minPrice = Arr::get($filters, 'min_price');
            if ($minPrice !== null && is_numeric($minPrice) && $price < (float) $minPrice) {
                return false;
            }

            $maxPrice = Arr::get($filters, 'max_price');
            if ($maxPrice !== null && is_numeric($maxPrice) && $price > (float) $maxPrice) {
                return false;
            }

            $inStock = Arr::get($filters, 'in_stock');
            if (($inStock === true || $inStock === 1 || $inStock === '1' || $inStock === 'true')
                && (int) ($product->stock_on_hand ?? 0) <= 0) {
                return false;
            }

            $brand = trim((string) Arr::get($filters, 'brand', ''));
            $attributes = is_array($product->attributes ?? null) ? $product->attributes : [];
            if ($brand !== '') {
                $brandValue = $attributes['brand'] ?? null;
                if (is_array($brandValue)) {
                    if (! in_array($brand, array_map('strval', $brandValue), true)) {
                        return false;
                    }
                } elseif ((string) $brandValue !== $brand) {
                    return false;
                }
            }

            $selectedAttributes = Arr::get($filters, 'attributes', []);
            if (is_array($selectedAttributes)) {
                foreach ($selectedAttributes as $key => $value) {
                    $normalizedValue = trim((string) (is_array($value) ? reset($value) : $value));
                    if ($normalizedValue === '') {
                        continue;
                    }

                    $attributeValue = $attributes[$key] ?? null;
                    if (is_array($attributeValue)) {
                        if (! in_array($normalizedValue, array_map('strval', $attributeValue), true)) {
                            return false;
                        }
                        continue;
                    }

                    if ((string) $attributeValue !== $normalizedValue) {
                        return false;
                    }
                }
            }

            return true;
        })->values();

        return $this->sortResolvedProducts($filtered, Arr::get($filters, 'sort'));
    }

    private function sortResolvedProducts(SupportCollection $products, ?string $sort): SupportCollection
    {
        $sort = is_string($sort) ? trim($sort) : null;

        return match ($sort) {
            'price_asc' => $products->sortBy(fn ($product) => (float) ($product->selling_price ?? 0))->values(),
            'price_desc' => $products->sortByDesc(fn ($product) => (float) ($product->selling_price ?? 0))->values(),
            'rating' => $products->sortByDesc(fn ($product) => (float) ($product->reviews_avg_rating ?? 0))->values(),
            'popularity', 'popular' => $products->sortByDesc(fn ($product) => (int) ($product->reviews_count ?? 0))->values(),
            'featured' => $products
                ->sortByDesc(fn ($product) => [
                    (int) ($product->is_featured ?? false),
                    optional($product->created_at)?->getTimestamp() ?? 0,
                ])
                ->values(),
            default => $products->sortByDesc(fn ($product) => optional($product->created_at)?->getTimestamp() ?? 0)->values(),
        };
    }

    private function filtersFromResolvedProducts(SupportCollection $products): array
    {
        $priceValues = $products
            ->map(function ($product): ?float {
                $sellingPrice = is_array($product)
                    ? ($product['selling_price'] ?? $product['price'] ?? null)
                    : ($product->selling_price ?? null);

                if ($sellingPrice !== null && is_numeric($sellingPrice)) {
                    return (float) $sellingPrice;
                }

                return null;
            })
            ->filter(fn ($value): bool => $value !== null)
            ->values();

        return [
            'price_range' => [
                'min' => $priceValues->isNotEmpty() ? round((float) $priceValues->min(), 2) : null,
                'max' => $priceValues->isNotEmpty() ? round((float) $priceValues->max(), 2) : null,
            ],
            ...app(ProductMetaExtractor::class)->extract($products->all()),
        ];
    }

    /**
     * @param array<int,int> $rootIds
     * @return array<int,int>
     */
    private function descendantCategoryIds(array $rootIds): array
    {
        $rootIds = collect($rootIds)->map(fn ($id) => (int) $id)->filter()->values()->all();
        if ($rootIds === []) {
            return [];
        }

        $all = $rootIds;
        $frontier = $rootIds;

        // BFS over parent_id until we stop finding children.
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

    private function parseScheduleDate($value, string $timezone): ?Carbon
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->timezone($timezone);
        }

        try {
            return Carbon::parse($value, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    private function sliceToLimit($collection, ?int $limit)
    {
        if (! $limit) {
            return $collection;
        }
        return $collection->take($limit)->values();
    }

    private function normalizeLimit(mixed $limit): ?int
    {
        if ($limit === null || $limit === '') {
            return null;
        }

        $normalized = (int) $limit;

        return $normalized > 0 ? $normalized : null;
    }

    private function shouldFallbackToManualProducts(string $mode, array $rules, array $manualIds): bool
    {
        if ($mode !== 'rules' || empty($manualIds)) {
            return false;
        }

        return ! $this->hasMeaningfulRules($rules);
    }

    private function hasMeaningfulRules(array $rules): bool
    {
        foreach ($rules as $value) {
            if (is_array($value) && ! empty($value)) {
                return true;
            }

            if (! is_array($value) && $value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    private function paginateRuleProducts(
        array $rules,
        array $excludeIds,
        ?string $locale,
        int $perPage,
        int $page,
        ?int $limit
    ): LengthAwarePaginator {
        $query = $this->buildRuleQuery($rules, $excludeIds, $locale);
        $total = (clone $query)->count();

        if ($limit !== null) {
            $total = min($total, $limit);
        }

        $offset = max(0, ($page - 1) * $perPage);
        if ($offset >= $total) {
            return $this->paginateCollection(collect(), $perPage, $page, $total);
        }

        $remaining = $total - $offset;
        $items = $query
            ->offset($offset)
            ->limit(min($perPage, $remaining))
            ->get();

        return $this->paginateCollection($items, $perPage, $page, $total);
    }

    private function paginateHybridFilteredProducts(
        array $rules,
        array $manualIds,
        ?string $locale,
        array $filters,
        int $perPage,
        int $page,
        ?int $limit
    ): LengthAwarePaginator {
        $manualCountQuery = $this->buildHybridManualQuery($manualIds, $filters, false, $locale);
        $ruleCountQuery = $this->buildRuleQuery($rules, $manualIds, $locale, $filters, false);

        $manualTotal = $manualCountQuery ? (clone $manualCountQuery)->count() : 0;
        $ruleTotal = (clone $ruleCountQuery)->count();
        $total = $manualTotal + $ruleTotal;

        if ($limit !== null) {
            $total = min($total, $limit);
        }

        $offset = max(0, ($page - 1) * $perPage);
        if ($offset >= $total) {
            return $this->paginateCollection(collect(), $perPage, $page, $total);
        }

        $candidateLimit = min($total, $offset + $perPage);
        $manualItems = collect();
        if ($manualCountQuery && $candidateLimit > 0) {
            $manualItems = $this->applyCandidateLimit($this->buildHybridManualQuery($manualIds, $filters, true, $locale), $candidateLimit)->get();
        }

        $ruleItems = collect();
        if ($candidateLimit > 0) {
            $ruleItems = $this->applyCandidateLimit($this->buildRuleQuery($rules, $manualIds, $locale, $filters), $candidateLimit)->get();
        }

        $items = $this->sortResolvedProducts($manualItems->concat($ruleItems)->values(), Arr::get($filters, 'sort'))
            ->slice($offset, $perPage)
            ->values();

        return $this->paginateCollection($items, $perPage, $page, $total);
    }

    private function paginateHybridProducts(
        array $rules,
        array $manualIds,
        ?string $locale,
        int $perPage,
        int $page,
        ?int $limit
    ): LengthAwarePaginator {
        $ruleQuery = $this->buildRuleQuery($rules, $manualIds, $locale);
        $manualCount = $limit !== null ? min(count($manualIds), $limit) : count($manualIds);
        $ruleCount = (clone $ruleQuery)->count();
        $total = $manualCount + $ruleCount;

        if ($limit !== null) {
            $total = min($total, $limit);
        }

        $offset = max(0, ($page - 1) * $perPage);
        if ($offset >= $total) {
            return $this->paginateCollection(collect(), $perPage, $page, $total);
        }

        $items = collect();

        if ($offset < $manualCount) {
            $items = $this->loadProductsPageByIds($manualIds, $offset, $perPage, $limit, $locale);
        }

        $remaining = $perPage - $items->count();
        if ($remaining > 0) {
            $ruleOffset = max(0, $offset - $manualCount);
            $availableRuleTotal = max(0, $total - min($manualCount, $total));
            if ($ruleOffset < $availableRuleTotal) {
                $ruleItems = $ruleQuery
                    ->offset($ruleOffset)
                    ->limit(min($remaining, $availableRuleTotal - $ruleOffset))
                    ->get();
                $items = $items->concat($ruleItems)->values();
            }
        }

        return $this->paginateCollection($items, $perPage, $page, $total);
    }

    private function paginateManualProducts(array $manualIds, int $perPage, int $page, ?int $limit = null, ?string $locale = null): LengthAwarePaginator
    {
        $total = $limit !== null ? min(count($manualIds), $limit) : count($manualIds);
        $offset = max(0, ($page - 1) * $perPage);

        if ($offset >= $total) {
            return $this->paginateCollection(collect(), $perPage, $page, $total);
        }

        $items = $this->loadProductsPageByIds($manualIds, $offset, $perPage, $limit, $locale);

        return $this->paginateCollection($items, $perPage, $page, $total);
    }

    private function loadProductsPageByIds(array $ids, int $offset, int $perPage, ?int $limit = null, ?string $locale = null)
    {
        if ($limit !== null) {
            $ids = array_slice($ids, 0, $limit);
        }

        if ($ids === []) {
            return collect();
        }

        $pageIds = array_slice($ids, $offset, $perPage);

        if ($pageIds === []) {
            return collect();
        }

        return $this->loadProductsByIds($pageIds, $locale);
    }

    private function paginateQueryResults(?Builder $query, int $perPage, int $page, ?int $limit = null): LengthAwarePaginator
    {
        if (! $query) {
            return $this->paginateCollection(collect(), $perPage, $page, 0);
        }

        $total = (clone $query)->count();
        if ($limit !== null) {
            $total = min($total, $limit);
        }

        $offset = max(0, ($page - 1) * $perPage);
        if ($offset >= $total) {
            return $this->paginateCollection(collect(), $perPage, $page, $total);
        }

        $remaining = $total - $offset;
        $items = $query
            ->offset($offset)
            ->limit(min($perPage, $remaining))
            ->get();

        return $this->paginateCollection($items, $perPage, $page, $total);
    }

    private function applyCandidateLimit(?Builder $query, int $candidateLimit): Builder
    {
        return $query->limit(max(1, $candidateLimit));
    }

    private function paginateCollection($items, int $perPage, int $page, ?int $total = null): LengthAwarePaginator
    {
        $collection = collect($items)->values();

        return new LengthAwarePaginator(
            $collection,
            $total ?? $collection->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }
}
