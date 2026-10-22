<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Category;
use App\Models\StorefrontCollection;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class InspectStorefrontCollection extends Command
{
    protected $signature = 'storefront:inspect-collection
                            {slug : Storefront collection slug (e.g. women)}
                            {--locale= : Locale to resolve products for (default: app locale)}
                            {--limit=50 : Number of products to sample}
                            {--page= : Page to sample when using pagination (default 1)}
                            {--paginate : Use paginateResolvedProducts (closer to storefront view)}
                            {--json : Output JSON instead of a table}';

    protected $description = 'Inspect a storefront collection resolution (products + category roots) to debug mixed collections.';

    public function handle(): int
    {
        $slug = trim((string) $this->argument('slug'));
        $locale = (string) ($this->option('locale') ?: app()->getLocale());
        $limit = max(1, (int) $this->option('limit'));
        $page = max(1, (int) ($this->option('page') ?: 1));
        $usePaginate = (bool) $this->option('paginate');
        $asJson = (bool) $this->option('json');

        /** @var StorefrontCollection|null $collection */
        $collection = StorefrontCollection::query()->where('slug', $slug)->first();
        if (! $collection) {
            $this->error("Collection not found: {$slug}");
            return self::FAILURE;
        }

        $rules = is_array($collection->rules) ? $collection->rules : [];
        $allowedSlugs = $this->normalizeStringArray(Arr::get($rules, 'category_slugs', []));
        $excludedSlugs = $this->normalizeStringArray(Arr::get($rules, 'exclude_category_slugs', []));

        $products = $usePaginate
            ? $collection->paginateResolvedProducts($locale, perPage: $limit, page: $page)->getCollection()
            : $collection->resolveProducts($locale, $limit);

        // Ensure we have category loaded for root-path output.
        // `resolveProducts()` returns an Eloquent collection, but `paginateResolvedProducts()->getCollection()`
        // can be a base Support\Collection. Normalize to Eloquent collection so we can call loadMissing().
        if (! $products instanceof EloquentCollection) {
            $products = new EloquentCollection($products->all());
        }

        $products->loadMissing(['category', 'category.parent']);

        $rows = $products->map(function ($product) use ($allowedSlugs, $excludedSlugs) {
            $category = $product->category;
            $path = $this->categoryPath($category);
            $rootSlug = $path[0]['slug'] ?? null;
            $rootName = $path[0]['name'] ?? null;

            $reason = null;
            if ($excludedSlugs !== [] && $rootSlug && in_array($rootSlug, $excludedSlugs, true)) {
                $reason = 'excluded_root';
            } elseif ($allowedSlugs !== [] && (! $rootSlug || ! in_array($rootSlug, $allowedSlugs, true))) {
                $reason = 'outside_allowed_roots';
            }

            return [
                'product_id' => (int) $product->id,
                'product' => (string) ($product->name ?? ''),
                'category_id' => $category ? (int) $category->id : null,
                'category_slug' => $category?->slug,
                'root_slug' => $rootSlug,
                'root_name' => $rootName,
                'path' => implode(' > ', array_map(fn ($p) => (string) ($p['slug'] ?? ''), $path)),
                'mismatch' => $reason !== null,
                'mismatch_reason' => $reason,
            ];
        })->values();

        $payload = [
            'collection' => [
                'id' => (int) $collection->id,
                'slug' => (string) $collection->slug,
                'title' => (string) $collection->title,
                'selection_mode' => (string) ($collection->selection_mode ?: 'rules'),
                'product_limit' => $collection->product_limit,
                'sort_by' => $collection->sort_by,
                'rules' => $rules,
            ],
            'sample' => [
                'count' => $rows->count(),
                'allowed_root_slugs' => $allowedSlugs,
                'excluded_root_slugs' => $excludedSlugs,
                'mismatch_count' => $rows->where('mismatch', true)->count(),
            ],
            'products' => $rows->all(),
        ];

        if ($asJson) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info("Collection: {$collection->slug} ({$collection->title})");
        $this->line('selection_mode=' . ($collection->selection_mode ?: 'rules')
            . ' sort_by=' . ($collection->sort_by ?: 'default')
            . ' product_limit=' . ($collection->product_limit ?? 'null'));
        $this->line('allowed_roots=' . ($allowedSlugs ? implode(',', $allowedSlugs) : '(none)')
            . ' excluded_roots=' . ($excludedSlugs ? implode(',', $excludedSlugs) : '(none)'));
        $this->line('sample_count=' . $rows->count() . ' mismatches=' . $rows->where('mismatch', true)->count());

        $this->table(
            ['id', 'product', 'cat', 'root', 'mismatch', 'reason', 'path'],
            $rows->map(fn (array $r) => [
                $r['product_id'],
                mb_strimwidth($r['product'], 0, 42, '...'),
                $r['category_slug'] ?? $r['category_id'],
                $r['root_slug'] ?? $r['root_name'],
                $r['mismatch'] ? 'yes' : 'no',
                $r['mismatch_reason'],
                mb_strimwidth($r['path'], 0, 70, '...'),
            ])->all()
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name:string|null,slug:string|null}>
     */
    private function categoryPath(?Category $category): array
    {
        if (! $category) {
            return [];
        }

        $path = [];
        $seen = [];
        $current = $category;

        for ($i = 0; $i < 20; $i++) {
            if (! $current) {
                break;
            }

            if (isset($seen[$current->id])) {
                break;
            }
            $seen[$current->id] = true;

            $path[] = [
                'name' => $current->name,
                'slug' => $current->slug,
            ];

            $current->loadMissing('parent');
            $current = $current->parent;
        }

        return array_reverse($path);
    }

    /**
     * @return array<int,string>
     */
    private function normalizeStringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->map(fn (string $v) => strtolower($v))
            ->unique()
            ->values()
            ->all();
    }
}
