<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

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
        'manual_products',
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
        'manual_products' => 'array',
    ];

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
        $manual = $this->manual_products ?? [];
        return collect($manual)
            ->filter(fn ($row) => is_array($row) && ! empty($row['product_id']))
            ->pluck('product_id')
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
            $manualProducts = $this->loadProductsByIds($manualIds);
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

    private function loadRuleProducts(array $rules, array $excludeIds, ?int $limit, ?string $locale)
    {
        $query = $this->buildRuleQuery($rules, $excludeIds, $locale);
        if ($limit) {
            $query->limit($limit);
        }
        return $query->get();
    }

    private function loadProductsByIds(array $ids)
    {
        if (empty($ids)) {
            return collect();
        }

        $orderMap = collect($this->manual_products ?? [])
            ->filter(fn ($row) => is_array($row) && ! empty($row['product_id']))
            ->mapWithKeys(function ($row, $index) {
                return [(int) $row['product_id'] => (int) ($row['position'] ?? $index)];
            })
            ->all();

        $products = Product::query()
            ->whereIn('id', $ids)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->get();

        return $products->sortBy(function ($product) use ($orderMap) {
            return $orderMap[$product->id] ?? 9999;
        })->values();
    }

    private function buildRuleQuery(array $rules, array $excludeIds, ?string $locale): Builder
    {
        $query = Product::query()
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        $isActive = Arr::get($rules, 'is_active', true);
        if ($isActive !== null) {
            $query->where('is_active', (bool) $isActive);
        }

        $categoryIds = Arr::get($rules, 'category_ids');
        if (is_array($categoryIds) && count($categoryIds) > 0) {
            $query->whereIn('category_id', $categoryIds);
        }

        $categorySlugs = Arr::get($rules, 'category_slugs');
        if (is_array($categorySlugs) && count($categorySlugs) > 0) {
            $query->whereHas('category', function (Builder $builder) use ($categorySlugs) {
                $builder->whereIn('slug', $categorySlugs);
            });
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
        if ($minRating !== null && is_numeric($minRating)) {
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

        $includeIds = Arr::get($rules, 'include_product_ids');
        if (is_array($includeIds) && count($includeIds) > 0) {
            $query->whereIn('id', $includeIds);
        }

        $excludeIds = array_merge($excludeIds, Arr::get($rules, 'exclude_product_ids', []));
        if (! empty($excludeIds)) {
            $query->whereNotIn('id', array_unique($excludeIds));
        }

        $sort = Arr::get($rules, 'sort') ?: $this->sort_by;
        $sortable = [
            'price_asc' => ['selling_price', 'asc'],
            'price_desc' => ['selling_price', 'desc'],
            'newest' => ['created_at', 'desc'],
            'rating' => ['reviews_avg_rating', 'desc'],
            'popularity' => ['reviews_count', 'desc'],
            'featured' => ['is_featured', 'desc'],
        ];

        if ($sort === 'random') {
            $query->inRandomOrder();
        } elseif ($sort && isset($sortable[$sort])) {
            [$field, $direction] = $sortable[$sort];
            $query->orderBy($field, $direction);
            if ($sort === 'featured') {
                $query->orderBy('created_at', 'desc');
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query;
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
}
