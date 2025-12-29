<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

use App\Domain\Products\Models\Category;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Jobs\GenerateProductSeoJob;
use App\Jobs\TranslateProductJob;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CjProductImportService
{
    public function __construct(
        private readonly CJDropshippingClient $client,
        private readonly CjProductMediaService $mediaService,
    ) {
    }

    public function importByLookup(string $lookupType, string $lookupValue, array $options = []): ?Product
    {
        $productResp = $this->client->getProductBy([$lookupType => $lookupValue]);
        $productData = $productResp->data ?? null;

        if (! is_array($productData) || $productData === []) {
            return null;
        }

        return $this->importFromPayload($productData, null, $options);
    }

    public function importByPid(string $pid, array $options = []): ?Product
    {
        $productResp = $this->client->getProduct($pid);
        $productData = $productResp->data ?? null;

        if (! is_array($productData) || $productData === []) {
            return null;
        }

        return $this->importFromPayload($productData, null, $options);
    }

    public function importFromPayload(array $productData, ?array $variants = null, array $options = []): ?Product
    {
        $pid = $this->resolvePid($productData);
        if ($pid === '') {
            return null;
        }

        $shipTo = strtoupper((string) ($options['shipToCountry'] ?? ''));
        if ($shipTo !== '') {
            $warehouseCountries = $this->extractWarehouseCountries($productData, $variants);
            // If we can’t infer warehouses, allow import by default to avoid skipping good products.
            if ($warehouseCountries !== [] && ! in_array($shipTo, $warehouseCountries, true)) {
                Log::info('CJ product skipped due to ship-to country filter', [
                    'pid' => $pid,
                    'ship_to' => $shipTo,
                    'warehouses' => $warehouseCountries,
                ]);
                return null;
            }
        }

        $product = Product::query()->where('cj_pid', $pid)->first();
    $isNewProduct = $product === null;

        $respectSyncFlag = (bool) ($options['respectSyncFlag'] ?? true);
        $defaultSyncEnabled = (bool) ($options['defaultSyncEnabled'] ?? true);
        $respectLocks = (bool) ($options['respectLocks'] ?? true);
        if ($product && $respectSyncFlag && $product->cj_sync_enabled === false) {
            return $product;
        }

        if ($product && ! ($options['updateExisting'] ?? true)) {
            return $product;
        }

        $lockPrice = $respectLocks && (bool) ($product?->cj_lock_price);
        $lockDescription = $respectLocks && (bool) ($product?->cj_lock_description);
        $lockImages = $respectLocks && (bool) ($product?->cj_lock_images);
        $lockVariants = $respectLocks && (bool) ($product?->cj_lock_variants);

        if ($variants === null) {
            $variantResp = $this->client->getVariantsByPid($pid);
            $variants = $variantResp->data ?? [];
        }

        $category = $this->resolveCategoryFromPayload($productData);

        $name = $productData['productNameEn'] ?? $productData['productName'] ?? ($productData['name'] ?? 'CJ Product');
        $slug = Str::slug($name . '-' . $pid);
        $price = $productData['productSellPrice'] ?? null;
        $priceValue = is_numeric($price) ? (float) $price : null;
        $incomingDescription = $this->mediaService->cleanDescription(
            $productData['descriptionEn']
                ?? $productData['productDescriptionEn']
                ?? $productData['description']
                ?? $productData['productDescription']
                ?? null
        );
        $description = $lockDescription ? ($product?->description ?? $incomingDescription) : $incomingDescription;

        $payloadAttributes = is_array($productData['attributes'] ?? null) ? $productData['attributes'] : [];
        $existingAttributes = is_array($product?->attributes) ? $product->attributes : [];

        $attributes = array_merge(
            $existingAttributes,
            $payloadAttributes,
            [
                'cj_pid' => $pid,
                'cj_payload' => $productData,
            ]
        );

        $payload = [
            'name' => $name,
            'category_id' => $category?->id,
            'description' => $description,
            'selling_price' => $lockPrice ? ($product?->selling_price ?? 0) : ($priceValue ?? ($product?->selling_price ?? 0)),
            'cost_price' => $lockPrice ? ($product?->cost_price ?? 0) : ($priceValue ?? ($product?->cost_price ?? 0)),
            'currency' => $productData['currency'] ?? 'USD',
            'attributes' => $attributes,
            'source_url' => $productData['productUrl'] ?? $productData['sourceUrl'] ?? null,
            'cj_synced_at' => now(),
        ];

        $syncVariants = ($options['syncVariants'] ?? true) === true && ! $lockVariants;
        $syncImages = ($options['syncImages'] ?? true) === true && ! $lockImages;
        $imagesUpdated = false;
        $videosUpdated = false;

        $changedFields = $product
            ? $this->diffFields($product, [
                'name' => $payload['name'],
                'description' => $payload['description'],
                'selling_price' => $payload['selling_price'],
                'cost_price' => $payload['cost_price'],
                'category_id' => $payload['category_id'],
                'currency' => $payload['currency'],
                'source_url' => $payload['source_url'],
            ])
            : ['created'];

        if ($syncVariants) {
            $changedFields[] = 'variants';
        }

        $payload['cj_last_payload'] = $productData;
        $payload['cj_last_changed_fields'] = array_values(array_unique($changedFields));

        if (! $product) {
            $payload['cj_pid'] = $pid;
            $payload['slug'] = $slug;
            $payload['status'] = 'active';
            $payload['is_active'] = true;
            $payload['is_featured'] = false;
            $payload['cj_sync_enabled'] = $defaultSyncEnabled;
            $product = Product::create($payload);
        } else {
            $product->fill($payload);
            if (! $product->slug) {
                $product->slug = $slug;
            }
            $product->save();
        }

        $shouldGenerateSeo = ($options['generateSeo'] ?? true) === true;
        if ($shouldGenerateSeo && (! $product->meta_title || ! $product->meta_description)) {
            try {
                GenerateProductSeoJob::dispatch((int) $product->id, 'en', false);
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch SEO job', ['product_id' => $product->id, 'error' => $e->getMessage()]);
            }
        }

        if ($syncVariants) {
            try {
                $this->syncVariants($product, $variants, $pid);
            } catch (\Throwable $e) {
                Log::warning('Failed to sync variants for product', ['cj_pid' => $pid, 'error' => $e->getMessage()]);
            }
        }

        if ($syncImages) {
            try {
                $imagesUpdated = $this->mediaService->syncImages($product, $productData, $variants);
            } catch (\Throwable $e) {
                Log::warning('Failed to sync images for product', ['cj_pid' => $pid, 'error' => $e->getMessage()]);
                $imagesUpdated = false;
            }

            try {
                $videosUpdated = $this->mediaService->syncVideos($product, $productData, $variants);
            } catch (\Throwable $e) {
                Log::warning('Failed to sync videos for product', ['cj_pid' => $pid, 'error' => $e->getMessage()]);
                $videosUpdated = false;
            }
        }

        if ($imagesUpdated || $videosUpdated) {
            if ($imagesUpdated) {
                $changedFields[] = 'images';
            }
            if ($videosUpdated) {
                $changedFields[] = 'videos';
            }

            $product->update([
                'cj_last_changed_fields' => array_values(array_unique($changedFields)),
            ]);
        }

        // Trigger translations when a product is newly imported or translatable fields changed
        $shouldTranslate = ($options['translate'] ?? true) === true;
        $translatableFields = ['created', 'name', 'description', 'variants'];
        $hasTranslatableChange = $isNewProduct || array_intersect($translatableFields, $changedFields) !== [];

        if ($shouldTranslate && $hasTranslatableChange) {
            try {
                TranslateProductJob::dispatch(
                    (int) $product->id,
                    $this->resolveTranslationLocales(),
                    $this->resolveTranslationSourceLocale(),
                    false
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch translation job after CJ import', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $product;
    }

    public function syncMedia(Product $product, array $options = []): bool
    {
        if (! $product->cj_pid) {
            return false;
        }

        $respectSyncFlag = (bool) ($options['respectSyncFlag'] ?? true);
        $respectLocks = (bool) ($options['respectLocks'] ?? true);

        if ($respectSyncFlag && $product->cj_sync_enabled === false) {
            return false;
        }

        if ($respectLocks && $product->cj_lock_images) {
            return false;
        }

        $productResp = $this->client->getProduct($product->cj_pid);
        $productData = $productResp->data ?? null;

        if (! is_array($productData) || $productData === []) {
            return false;
        }

        $variantResp = $this->client->getVariantsByPid($product->cj_pid);
        $variants = $variantResp->data ?? [];

        $imagesUpdated = $this->mediaService->syncImages($product, $productData, $variants);
        $videosUpdated = $this->mediaService->syncVideos($product, $productData, $variants);

        if (! $imagesUpdated && ! $videosUpdated) {
            return false;
        }

        $changedFields = is_array($product->cj_last_changed_fields) ? $product->cj_last_changed_fields : [];

        if ($imagesUpdated) {
            $changedFields[] = 'images';
        }

        if ($videosUpdated) {
            $changedFields[] = 'videos';
        }

        $product->update([
            'cj_last_payload' => $productData,
            'cj_last_changed_fields' => array_values(array_unique($changedFields)),
            'cj_synced_at' => now(),
        ]);

        return true;
    }

    public function syncMyProducts(int $startPage = 1, int $pageSize = 24, int $maxPages = 50, bool $forceUpdate = false): array
    {
        $imported = 0;
        $processed = 0;
        $errors = 0;
        $lastPage = $startPage;

        for ($i = 0; $i < $maxPages; $i++) {
            $page = $startPage + $i;
            $lastPage = $page;

            $resp = $this->client->listMyProducts([
                'pageNum' => $page,
                'pageSize' => $pageSize,
            ]);

            // Normalize possible CJ response shapes to an array of product items.
            $raw = $resp->data ?? [];
            $content = [];

            if (is_array($raw)) {
                // Case: content is an array of pages where each page may contain a productList
                if (! empty($raw['content']) && is_array($raw['content'])) {
                    foreach ($raw['content'] as $entry) {
                        if (is_array($entry) && isset($entry['productList']) && is_array($entry['productList'])) {
                            $content = array_merge($content, $entry['productList']);
                        } elseif (is_array($entry)) {
                            $content[] = $entry;
                        }
                    }
                } elseif (! empty($raw['productList']) && is_array($raw['productList'])) {
                    $content = $raw['productList'];
                } elseif (! empty($raw['content']) && is_array($raw['content'])) {
                    $content = $raw['content'];
                } else {
                    // Fallback: if raw looks like a list of items, treat it as such.
                    $numericKeys = array_filter(array_keys($raw), 'is_int');
                    if ($numericKeys !== []) {
                        $content = $raw;
                    }
                }
            }

            if (! is_array($content) || $content === []) {
                break;
            }

            foreach ($content as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $pid = (string) ($item['pid'] ?? $item['id'] ?? $item['productId'] ?? $item['product_id'] ?? '');
                if ($pid === '') {
                    continue;
                }

                $processed++;

                try {
                    $product = $this->importByPid($pid, [
                        'respectSyncFlag' => ! $forceUpdate,
                        'defaultSyncEnabled' => true,
                        'shipToCountry' => (string) (config('services.cj.ship_to_default') ?? ''),
                    ]);

                    if ($product) {
                        $imported++;
                    }
                } catch (\Throwable) {
                    $errors++;
                }
            }

            if (count($content) < $pageSize) {
                break;
            }
        }

        return [
            'imported' => $imported,
            'processed' => $processed,
            'errors' => $errors,
            'last_page' => $lastPage,
        ];
    }

    private function syncVariants(Product $product, mixed $variants, string $pid): void
    {
        if (is_array($variants) && $variants !== []) {
            foreach ($variants as $variant) {
                try {
                    if (! is_array($variant)) {
                        continue;
                    }

                    $vid = (string) ($variant['vid'] ?? '');
                    $sku = $variant['variantSku'] ?? $variant['sku'] ?? null;

                    if (! $sku && ! $vid) {
                        continue;
                    }

                    ProductVariant::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'cj_vid' => $vid ?: null,
                            'sku' => $sku,
                        ],
                        [
                            'title' => $variant['variantName'] ?? ($variant['variantKey'] ?? 'Variant'),
                            'price' => is_numeric($variant['variantSellPrice'] ?? null) ? (float) $variant['variantSellPrice'] : ($product->selling_price ?? 0),
                            'cost_price' => is_numeric($variant['variantSellPrice'] ?? null) ? (float) $variant['variantSellPrice'] : ($product->cost_price ?? 0),
                            'currency' => $product->currency ?? 'USD',
                            'metadata' => [
                                'cj_vid' => $vid,
                                'cj_variant' => $variant,
                            ],
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed to sync single variant', ['product_id' => $product->id, 'variant' => $variant, 'error' => $e->getMessage()]);
                }
            }

            return;
        }

        if (! $product->variants()->exists()) {
            try {
                $product->variants()->create([
                    'title' => 'Default',
                    'price' => $product->selling_price ?? 0,
                    'cost_price' => $product->cost_price ?? 0,
                    'currency' => $product->currency ?? 'USD',
                    'metadata' => [
                        'cj_pid' => $pid,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to create default variant', ['product_id' => $product->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Determine which locales should be generated via the translation pipeline.
     *
     * @return array<int, string>
     */
    private function resolveTranslationLocales(): array
    {
        $configured = config('services.translation_locales', ['en', 'fr']);

        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        if (! is_array($configured)) {
            return ['en', 'fr'];
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            fn ($locale) => strtolower(trim((string) $locale)),
            $configured
        ), fn ($locale) => $locale !== '')));

        return $normalized === [] ? ['en', 'fr'] : $normalized;
    }

    private function resolveTranslationSourceLocale(): string
    {
        $source = strtolower(trim((string) config('services.translation_source_locale', 'en')));

        return $source !== '' ? $source : 'en';
    }

    private function resolvePid(array $productData): string
    {
        return (string) ($productData['pid']
            ?? $productData['productId']
            ?? $productData['product_id']
            ?? $productData['id']
            ?? '');
    }

    /**
     * Extract potential warehouse country codes from product/variant payloads.
     * Best-effort: if nothing is present, returns an empty array to avoid over-filtering.
     */
    private function extractWarehouseCountries(array $productData, mixed $variants): array
    {
        $candidates = [];

        $lists = [
            $productData['warehouses'] ?? null,
            $productData['warehouseList'] ?? null,
            $productData['globalWarehouseList'] ?? null,
            $productData['warehouseInfos'] ?? null,
        ];

        foreach ($lists as $list) {
            if (! is_array($list)) {
                continue;
            }

            foreach ($list as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $code = $item['countryCode']
                    ?? $item['country']
                    ?? $item['warehouseCountryCode']
                    ?? $item['warehouseCountry']
                    ?? null;

                if (is_string($code) && $code !== '') {
                    $candidates[] = strtoupper($code);
                }
            }
        }

        if (is_array($variants)) {
            foreach ($variants as $variant) {
                if (! is_array($variant)) {
                    continue;
                }

                $code = $variant['warehouseCountry'] ?? $variant['warehouseCountryCode'] ?? null;
                if (! $code) {
                    $warehouse = $variant['warehouse'] ?? null;
                    if (is_array($warehouse)) {
                        $code = $warehouse['countryCode'] ?? $warehouse['country'] ?? null;
                    }
                }

                if (is_string($code) && $code !== '') {
                    $candidates[] = strtoupper($code);
                }
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function resolveCategoryFromPayload(array $productData): ?Category
    {
        // V2 API: Use embedded category structure with proper IDs
        // oneCategoryId/Name, twoCategoryId/Name, categoryId/threeCategoryName
        $oneCategoryId = (string) ($productData['oneCategoryId'] ?? '');
        $oneCategoryName = (string) ($productData['oneCategoryName'] ?? '');
        $twoCategoryId = (string) ($productData['twoCategoryId'] ?? '');
        $twoCategoryName = (string) ($productData['twoCategoryName'] ?? '');
        $threeCategoryId = (string) ($productData['categoryId'] ?? '');
        $threeCategoryName = (string) ($productData['threeCategoryName'] ?? '');

        // If we have V2 API structure, build hierarchy with proper CJ IDs
        if ($oneCategoryId !== '' && $oneCategoryName !== '') {
            return $this->buildCategoryHierarchy([
                ['id' => $oneCategoryId, 'name' => $oneCategoryName, 'parent' => null],
                $twoCategoryId !== '' ? ['id' => $twoCategoryId, 'name' => $twoCategoryName, 'parent' => $oneCategoryId] : null,
                $threeCategoryId !== '' ? ['id' => $threeCategoryId, 'name' => $threeCategoryName, 'parent' => $twoCategoryId] : null,
            ]);
        }

        // Fallback: Legacy API or string-based categories
        $categoryId = (string) ($productData['categoryId'] ?? '');

        if ($categoryId !== '') {
            $existing = Category::query()->where('cj_id', $categoryId)->first();
            if ($existing) {
                return $existing;
            }
        }

        $rawName = $productData['categoryName']
            ?? $productData['categoryNameEn']
            ?? $productData['category_name']
            ?? null;

        if (! is_string($rawName) || $rawName === '') {
            return null;
        }

           // Handle both "/" and ">" separators used by different CJ APIs
           $rawName = str_replace(' > ', '/', $rawName);
           $segments = array_filter(array_map('trim', explode('/', $rawName)));
        if ($segments === []) {
            return null;
        }

        $parent = null;
        $category = null;
        $totalSegments = count($segments);

        foreach ($segments as $position => $segment) {
            if ($segment === '') {
                continue;
            }

            $slug = Str::slug($parent ? "{$parent->slug} {$segment}" : $segment);
            
            // Search first by name and parent_id (or both null for root categories)
            $category = Category::query()
                ->where('name', $segment)
                ->where('parent_id', $parent?->id)
                ->first();
            
            if (! $category) {
                $category = Category::create([
                    'name' => $segment,
                    'slug' => $slug,
                    'parent_id' => $parent?->id,
                ]);
            }

            if ($position === $totalSegments - 1 && $categoryId !== '' && $category->cj_id !== $categoryId) {
                $category->update(['cj_id' => $categoryId]);
            }

            $parent = $category;
        }

        return $category;
    }

    private function buildCategoryHierarchy(array $levels): ?Category
    {
        $parent = null;
        $category = null;

        foreach ($levels as $level) {
            if ($level === null) {
                continue;
            }

            $cjId = $level['id'] ?? null;
            $name = $level['name'] ?? '';
            $parentId = $level['parent'] ?? null;

            if ($name === '') {
                continue;
            }

            // First, try to find by CJ ID (most reliable)
            if ($cjId) {
                $category = Category::query()->where('cj_id', $cjId)->first();
                if ($category) {
                    $parent = $category;
                    continue;
                }
            }

            // Then try by name and parent
            $category = Category::query()
                ->where('name', $name)
                ->where('parent_id', $parent?->id)
                ->first();

            if (! $category) {
                $slug = Str::slug($parent ? "{$parent->slug} {$name}" : $name);
                $category = Category::create([
                    'name' => $name,
                    'slug' => $slug,
                    'parent_id' => $parent?->id,
                    'cj_id' => $cjId,
                ]);
            } elseif ($cjId && $category->cj_id !== $cjId) {
                // Update CJ ID if missing
                $category->update(['cj_id' => $cjId]);
            }

            $parent = $category;
        }

        return $category;
    }

    private function diffFields(Product $product, array $incoming): array
    {
        $changed = [];

        foreach ($incoming as $field => $value) {
            $current = $product->{$field};

            if (in_array($field, ['selling_price', 'cost_price'], true)) {
                $current = $current !== null ? (float) $current : null;
                $value = $value !== null ? (float) $value : null;
            }

            if ($current !== $value) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
