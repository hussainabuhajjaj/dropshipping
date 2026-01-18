<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

use App\Domain\Products\Models\Category;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Jobs\GenerateProductSeoJob;
use App\Jobs\TranslateProductJob;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Models\ProductReview;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CjProductImportService
{
    public function __construct(
        private readonly CJDropshippingClient  $client,
        private readonly CjProductMediaService $mediaService,
    )
    {
    }

    public function importByLookup(string $lookupType, string $lookupValue, array $options = []): ?Product
    {
        $productResp = $this->client->getProductBy([$lookupType => $lookupValue]);
        $productData = $productResp->data ?? null;

        if (!is_array($productData) || $productData === []) {
            return null;
        }

        return $this->importFromPayload($productData, null, $options);
    }

    public function importByPid(string $pid, array $options = []): ?Product
    {
        $productResp = $this->client->getProduct($pid);
        $productData = $productResp->data ?? null;

        if (!is_array($productData) || $productData === []) {
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

        $shipTo = strtoupper((string)($options['shipToCountry'] ?? ''));
        if ($shipTo !== '') {
            $warehouseCountries = $this->extractWarehouseCountries($productData, $variants);
            // If we can’t infer warehouses, allow import by default to avoid skipping good products.
            if ($warehouseCountries !== [] && !in_array($shipTo, $warehouseCountries, true)) {
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

        $respectSyncFlag = (bool)($options['respectSyncFlag'] ?? true);
        $defaultSyncEnabled = (bool)($options['defaultSyncEnabled'] ?? true);
        $respectLocks = (bool)($options['respectLocks'] ?? true);
        if ($product && $respectSyncFlag && $product->cj_sync_enabled === false) {
            return $product;
        }

        if ($product && !($options['updateExisting'] ?? true)) {
            return $product;
        }

        $lockPrice = $respectLocks && (bool)($product?->cj_lock_price);
        $lockDescription = $respectLocks && (bool)($product?->cj_lock_description);
        $lockImages = $respectLocks && (bool)($product?->cj_lock_images);
        $lockVariants = $respectLocks && (bool)($product?->cj_lock_variants);

        if ($variants === null) {
            $variantResp = $this->client->getVariantsByPid($pid);
            $variants = $variantResp->data ?? [];
        }

        $category = $this->resolveCategoryFromPayload($productData);

        $name = $productData['productNameEn'] ?? $productData['productName'] ?? ($productData['name'] ?? 'CJ Product');
        $slug = Str::slug($name . '-' . $pid);
        // Use first variant price if available, else fallback to productSellPrice
        $firstVariantPrice = null;
        if (is_array($variants) && count($variants) > 0) {
            $first = $variants[0];
            if (isset($first['variantSellPrice']) && is_numeric($first['variantSellPrice'])) {
                $firstVariantPrice = (float)$first['variantSellPrice'];
            }
        }
        $price = $productData['productSellPrice'] ?? null;
        $priceValue = is_numeric($firstVariantPrice) ? $firstVariantPrice : (is_numeric($price) ? (float)$price : null);
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

        $variantPayload = is_array($variants) ? $variants : ($existingAttributes['cj_variants'] ?? []);
        if (! is_array($variantPayload)) {
            $variantPayload = [];
        }

        $attributes = array_merge(
            $existingAttributes,
            $payloadAttributes,
            [
                'cj_pid' => $pid,
                'cj_payload' => $productData,
                'cj_variants' => $variantPayload,
            ]
        );

        // Set cost price as imported, selling price to 0
        $rawCost = $lockPrice ? ($product?->cost_price ?? 0) : ($priceValue ?? ($product?->cost_price ?? 0));
        $payload = [
            'name' => $name,
            'category_id' => $category?->id,
            'description' => $description,
            'selling_price' => 0,
            'cost_price' => $rawCost,
            'currency' => $productData['currency'] ?? 'USD',
            'attributes' => $attributes,
            'source_url' => $productData['productUrl'] ?? $productData['sourceUrl'] ?? null,
            'cj_synced_at' => now(),
            'default_fulfillment_provider_id' => 1,
        ];

        $syncVariants = ($options['syncVariants'] ?? true) === true && !$lockVariants;
        $syncImages = ($options['syncImages'] ?? true) === true && !$lockImages;
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

        if (!$product) {
            $payload['cj_pid'] = $pid;
            $payload['slug'] = $slug;
            $payload['status'] = 'active';
            $payload['is_active'] = true;
            $payload['is_featured'] = false;
            $payload['cj_sync_enabled'] = $defaultSyncEnabled;
            $product = Product::create($payload);
        } else {
            $product->fill($payload);
            if (!$product->slug) {
                $product->slug = $slug;
            }
            $product->save();
        }

        $shouldGenerateSeo = ($options['generateSeo'] ?? true) === true;
        if ($shouldGenerateSeo && (!$product->meta_title || !$product->meta_description)) {
            try {
                GenerateProductSeoJob::dispatch((int)$product->id, 'en', false);
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
                    (int)$product->id,
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

        $shouldSyncReviews = ($options['syncReviews'] ?? true) === true;
        if ($shouldSyncReviews) {
            try {
                $this->syncReviews($product, [
                    'throwOnFailure' => false,
                    'score' => $options['reviewScore'] ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to sync CJ product reviews after import', [
                    'product_id' => $product->id,
                    'pid' => $product->cj_pid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $product;
    }

    public function syncMedia(Product $product, array $options = []): bool
    {
        if (!$product->cj_pid) {
            return false;
        }

        $respectSyncFlag = (bool)($options['respectSyncFlag'] ?? true);
        $respectLocks = (bool)($options['respectLocks'] ?? true);

        if ($respectSyncFlag && $product->cj_sync_enabled === false) {
            return false;
        }

        if ($respectLocks && $product->cj_lock_images) {
            return false;
        }

        $productResp = $this->client->getProduct($product->cj_pid);
        $productData = $productResp->data ?? null;

        if (!is_array($productData) || $productData === []) {
            return false;
        }

        $variantResp = $this->client->getVariantsByPid($product->cj_pid);
        $variants = $variantResp->data ?? [];

        $imagesUpdated = $this->mediaService->syncImages($product, $productData, $variants);
        $videosUpdated = $this->mediaService->syncVideos($product, $productData, $variants);

        if (!$imagesUpdated && !$videosUpdated) {
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

    /**
     * Import CJ product reviews (comments) into product_reviews with no data loss.
     *
     * @return array{created:int,updated:int,fetched:int}
     */
    public function syncReviews(Product $product, array $options = []): array
    {
        if (! $product->cj_pid) {
            return ['created' => 0, 'updated' => 0, 'fetched' => 0];
        }

        $pageSize = (int) ($options['pageSize'] ?? 50);
        $pageSize = $pageSize > 0 ? min($pageSize, 100) : 50;
        $maxPages = (int) ($options['maxPages'] ?? 50);
        $maxPages = $maxPages > 0 ? $maxPages : 50;
        $score = isset($options['score']) ? (int) $options['score'] : null;
        $throwOnFailure = (bool) ($options['throwOnFailure'] ?? true);

        $created = 0;
        $updated = 0;
        $fetched = 0;

        $page = 1;
        $total = null;

        while ($page <= $maxPages) {
            $resp = $this->client->getProductReviews($product->cj_pid, $page, $pageSize, $score);

            if (! $resp->ok) {
                $message = $resp->message ?: 'CJ productComments request failed';

                if ($throwOnFailure) {
                    throw new \RuntimeException($message);
                }

                Log::warning('CJ product reviews request failed', [
                    'pid' => $product->cj_pid,
                    'page' => $page,
                    'pageSize' => $pageSize,
                    'score' => $score,
                    'message' => $message,
                    'requestId' => $resp->requestId ?? null,
                ]);

                break;
            }

            $data = is_array($resp->data) ? $resp->data : [];
            $list = $data['list'] ?? [];

            if ($total === null && isset($data['total'])) {
                $total = is_numeric($data['total']) ? (int) $data['total'] : null;
            }

            if (! is_array($list) || $list === []) {
                break;
            }

            $rows = [];
            $externalIds = [];

            foreach ($list as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $externalId = (string) ($entry['commentId'] ?? '');
                if ($externalId === '') {
                    continue;
                }

                $externalIds[] = $externalId;

                $ratingRaw = $entry['score'] ?? null;
                $rating = is_numeric($ratingRaw) ? (int) $ratingRaw : 5;
                $rating = max(1, min(5, $rating));

                $body = trim((string) ($entry['comment'] ?? ''));
                if ($body === '') {
                    $body = '[No comment]';
                }

                $title = trim((string) ($entry['commentUser'] ?? ''));
                $title = $title !== '' ? $title : null;

                $images = [];
                if (isset($entry['commentUrls']) && is_array($entry['commentUrls'])) {
                    $images = array_values(array_filter(array_map('strval', $entry['commentUrls'])));
                }

                $createdAt = now();
                if (! empty($entry['commentDate'])) {
                    try {
                        $createdAt = Carbon::parse((string) $entry['commentDate']);
                    } catch (\Throwable) {
                        // Keep fallback.
                    }
                }

                $rows[] = [
                    'product_id' => $product->id,
                    'customer_id' => null,
                    'order_id' => null,
                    'order_item_id' => null,
                    'rating' => $rating,
                    'title' => $title,
                    'body' => $body,
                    'status' => 'approved',
                    'images' => $images === [] ? null : json_encode($images, JSON_UNESCAPED_SLASHES),
                    'verified_purchase' => false,
                    'helpful_count' => 0,
                    'external_provider' => 'CJ',
                    'external_id' => $externalId,
                    'external_payload' => json_encode($entry, JSON_UNESCAPED_SLASHES),
                    'created_at' => $createdAt,
                    'updated_at' => now(),
                ];
            }

            if ($rows === []) {
                break;
            }

            $existing = ProductReview::query()
                ->where('external_provider', 'CJ')
                ->whereIn('external_id', $externalIds)
                ->pluck('external_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            $existingMap = array_fill_keys($existing, true);

            foreach ($externalIds as $id) {
                if (isset($existingMap[$id])) {
                    $updated++;
                } else {
                    $created++;
                }
            }

            DB::transaction(function () use ($rows): void {
                ProductReview::query()->upsert(
                    $rows,
                    ['external_provider', 'external_id'],
                    [
                        'product_id',
                        'rating',
                        'title',
                        'body',
                        'status',
                        'images',
                        'verified_purchase',
                        'helpful_count',
                        'external_payload',
                        'updated_at',
                    ]
                );
            });

            $fetched += count($rows);

            if ((bool) env('CJ_DEBUG', false)) {
                Log::debug('CJ reviews synced', [
                    'pid' => $product->cj_pid,
                    'page' => $page,
                    'fetched' => count($rows),
                    'total' => $total,
                    'requestId' => $resp->requestId ?? null,
                ]);
            }

            if ($total !== null && $fetched >= $total) {
                break;
            }

            $page++;
        }

        return ['created' => $created, 'updated' => $updated, 'fetched' => $fetched];
    }

    public function syncMyProducts(int $startPage = 1, int $pageSize = 100, int $maxPages = 10, bool $forceUpdate = false): array
    {
        $queued = 0;
        $processed = 0;
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
                if (!empty($raw['content']) && is_array($raw['content'])) {
                    foreach ($raw['content'] as $entry) {
                        if (is_array($entry) && isset($entry['productList']) && is_array($entry['productList'])) {
                            $content = array_merge($content, $entry['productList']);
                        } elseif (is_array($entry)) {
                            $content[] = $entry;
                        }
                    }
                } elseif (!empty($raw['productList']) && is_array($raw['productList'])) {
                    $content = $raw['productList'];
                } elseif (!empty($raw['content']) && is_array($raw['content'])) {
                    $content = $raw['content'];
                } else {
                    $numericKeys = array_filter(array_keys($raw), 'is_int');
                    if ($numericKeys !== []) {
                        $content = $raw;
                    }
                }
            }

            if (!is_array($content) || $content === []) {
                break;
            }

            foreach ($content as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $pid = (string)($item['pid'] ?? $item['id'] ?? $item['productId'] ?? $item['product_id'] ?? '');
                if ($pid === '') {
                    continue;
                }

                $processed++;

                // Dispatch import job for each product
                try {
                    \App\Jobs\ImportCjProductJob::dispatch($pid, [
                        'respectSyncFlag' => !$forceUpdate,
                        'defaultSyncEnabled' => true,
                        // 'shipToCountry' => (string) (config('services.cj.ship_to_default') ?? ''),
                    ]);
                    $queued++;
                } catch (\Throwable) {
                    // Optionally log or count errors
                }
            }

            if (count($content) < $pageSize) {
                break;
            }
        }

        return [
            'queued' => $queued,
            'processed' => $processed,
            'last_page' => $lastPage,
        ];
    }

    private function syncVariants(Product $product, mixed $variants, string $pid): void
    {
        if (is_array($variants) && $variants !== []) {
            foreach ($variants as $variant) {
                try {
                    if (!is_array($variant)) {
                        continue;
                    }

                    $vid = (string)($variant['vid'] ?? '');
                    $sku = $variant['variantSku'] ?? $variant['sku'] ?? null;

                    if (!$sku && !$vid) {
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
                            'price' => is_numeric($variant['variantSellPrice'] ?? null) ? (float)$variant['variantSellPrice'] : ($product->selling_price ?? 0),
                            'cost_price' => is_numeric($variant['variantSellPrice'] ?? null) ? (float)$variant['variantSellPrice'] : ($product->cost_price ?? 0),
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

        if (!$product->variants()->exists()) {
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

        if (!is_array($configured)) {
            return ['en', 'fr'];
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            fn($locale) => strtolower(trim((string)$locale)),
            $configured
        ), fn($locale) => $locale !== '')));

        return $normalized === [] ? ['en', 'fr'] : $normalized;
    }

    private function resolveTranslationSourceLocale(): string
    {
        $source = strtolower(trim((string)config('services.translation_source_locale', 'en')));

        return $source !== '' ? $source : 'en';
    }

    private function resolvePid(array $productData): string
    {
        return (string)($productData['pid']
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
            if (!is_array($list)) {
                continue;
            }

            foreach ($list as $item) {
                if (!is_array($item)) {
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
                if (!is_array($variant)) {
                    continue;
                }

                $code = $variant['warehouseCountry'] ?? $variant['warehouseCountryCode'] ?? null;
                if (!$code) {
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
        // Categories are pre-synced from CJ; never create categories here.
        // Prefer the deepest CJ category id (level 3), then fall back to level 2/1 ids.
        $candidateIds = [
            (string)($productData['categoryId'] ?? ''),
            (string)($productData['twoCategoryId'] ?? ''),
            (string)($productData['oneCategoryId'] ?? ''),
        ];

        foreach ($candidateIds as $cjId) {
            $cjId = trim($cjId);
            if ($cjId === '') {
                continue;
            }

            $category = Category::query()->where('cj_id', $cjId)->first();
            if ($category) {
                return $category;
            }
        }

        return null;
    }

    private function diffFields(Product $product, array $incoming): array
    {
        $changed = [];

        foreach ($incoming as $field => $value) {
            $current = $product->{$field};

            if (in_array($field, ['selling_price', 'cost_price'], true)) {
                $current = $current !== null ? (float)$current : null;
                $value = $value !== null ? (float)$value : null;
            }

            if ($current !== $value) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
