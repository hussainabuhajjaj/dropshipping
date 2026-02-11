<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

use App\Domain\Products\Models\Category;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Jobs\GenerateProductSeoJob;
use App\Jobs\TranslateProductJob;
use App\Jobs\TranslateProductsChunkJob;
use App\Jobs\GenerateProductSeoChunkJob;
use App\Jobs\SyncProductMediaChunkJob;
use App\Jobs\SyncProductVariantsChunkJob;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use Illuminate\Http\Client\ConnectionException;
use App\Models\ProductReview;
use App\Domain\Products\Services\PricingService;
use App\Services\ProductMarginLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Arr;

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
        try {
            $productResp = $this->client->getProduct($pid);
            $productData = $productResp->data ?? null;
        } catch (ApiException $e) {
            if ($this->isRemovedFromShelves($e)) {
                $this->markProductRemoved($pid, $e->getMessage());
                return null;
            }
            throw $e;
        }

        if (!is_array($productData) || $productData === []) {
            return null;
        }

        return $this->importFromPayload($productData, null, $options);
    }

    public function importFromPayload(array $productData, ?array $variants = null, array $options = []): ?Product
    {
        $productData = $this->normalizeProductPayload($productData);

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
            try {
                $variantResp = $this->client->getVariantsByPid($pid);
                $variants = $variantResp->data ?? [];
            } catch (ConnectionException $e) {
                Log::warning('CJ variant lookup timed out', ['pid' => $pid, 'error' => $e->getMessage()]);
                $variants = [];
            } catch (ApiException $e) {
                if ($this->isRemovedFromShelves($e)) {
                    $this->markProductRemoved($pid, $e->getMessage());
                    return null;
                }
                throw $e;
            }
        }

        $category = $this->resolveCategoryFromPayload($productData);

        $rawName = $productData['productNameEn'] ?? $productData['productName'] ?? ($productData['name'] ?? null);
        $name = $this->cleanProductName($rawName) ?: 'CJ Product ' . $pid;
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
        $incomingDescription = $this->cleanDescription(
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

        // Set cost price as imported, preserve selling price if price lock is enabled
        $rawCost = $lockPrice ? ($product?->cost_price ?? 0) : ($priceValue ?? ($product?->cost_price ?? 0));
        $pricing = PricingService::makeFromConfig();
        $minSell = is_numeric($rawCost) ? $pricing->minSellingPrice((float) $rawCost) : 0;
        $sellingPrice = $lockPrice && $product ? ($product->selling_price ?? 0) : 0;
        if ($sellingPrice <= 0 || $sellingPrice < $minSell) {
            $sellingPrice = $minSell;
        }
        $payload = [
            'name' => $name,
            'category_id' => $category?->id,
            'description' => $description,
            'selling_price' => $sellingPrice,
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
            $payload['status'] = 'draft';
            $payload['is_active'] = false;
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

        $logger = app(ProductMarginLogger::class);
        $product->refresh();
        $logger->logProduct($product, [
            'event' => 'imported',
            'source' => 'cj',
            'old_selling_price' => $product->getOriginal('selling_price'),
            'new_selling_price' => $product->selling_price,
            'old_status' => $product->getOriginal('status'),
            'new_status' => $product->status,
            'notes' => "CJ import {$pid}",
        ]);

        $product->loadMissing('variants');
        foreach ($product->variants as $variant) {
            $logger->logVariant($variant, [
                'event' => 'variant_imported',
                'source' => 'cj',
                'notes' => "Imported variant for {$pid}",
            ]);
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

            try {
                GenerateProductCompareAtJob::dispatch((int) $product->id, false);
            } catch (\Throwable $e) {
                Log::warning('Failed to queue compare-at generation', ['product_id' => $product->id, 'error' => $e->getMessage()]);
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
                )->onQueue('translations');
            } catch (\Throwable $e) {
                Log::warning('Translation failed during CJ import', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $shouldSyncReviews = ($options['syncReviews'] ?? true) === true;
        if ($shouldSyncReviews) {
            try {
                $reviewResult = $this->syncReviews($product, [
                    'throwOnFailure' => (bool) ($options['reviewThrowOnFailure'] ?? false),
                    'score' => $options['reviewScore'] ?? null,
                    'pageSize' => $options['reviewPageSize'] ?? null,
                    'maxPages' => $options['reviewMaxPages'] ?? null,
                ]);

                if (($reviewResult['fetched'] ?? 0) > 0 || (bool) env('CJ_DEBUG', false)) {
                    Log::info('CJ product reviews synced after import', [
                        'product_id' => $product->id,
                        'pid' => $product->cj_pid,
                        'fetched' => (int) ($reviewResult['fetched'] ?? 0),
                        'created' => (int) ($reviewResult['created'] ?? 0),
                        'updated' => (int) ($reviewResult['updated'] ?? 0),
                    ]);
                }
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

    /**
     * Bulk import multiple product payloads using a single upsert operation.
     * This method avoids per-product DB transactions and reduces churn when
     * importing large batches. It will optionally dispatch translation and SEO
     * chunk jobs for the affected products.
     *
     * @param array<int,array> $productPayloads
     * @return array{created:int,updated:int,processed:int}
     */
    public function importBulkFromPayloads(array $productPayloads, array $options = []): array
    {
        if (empty($productPayloads)) {
            return ['created' => 0, 'updated' => 0, 'processed' => 0];
        }

        $now = now();
        $rows = [];
        $pids = [];

        foreach ($productPayloads as $productData) {
            $productData = $this->normalizeProductPayload($productData);

            $pid = $this->resolvePid($productData);
            if ($pid === '') {
                continue;
            }

            $pids[] = $pid;

            $name = $this->cleanProductName($productData['productNameEn'] ?? $productData['productName'] ?? ($productData['name'] ?? null)) ?: 'CJ Product';
            $slug = Str::slug($name . '-' . $pid);

            $firstVariantPrice = null;
            $variants = $productData['variants'] ?? [];
            if (is_array($variants) && count($variants) > 0) {
                $first = $variants[0];
                if (isset($first['variantSellPrice']) && is_numeric($first['variantSellPrice'])) {
                    $firstVariantPrice = (float)$first['variantSellPrice'];
                }
            }

            $price = $productData['productSellPrice'] ?? null;
            $priceValue = is_numeric($firstVariantPrice) ? $firstVariantPrice : (is_numeric($price) ? (float)$price : null);

            $incomingDescription = $this->cleanDescription(
                $productData['descriptionEn']
                ?? $productData['productDescriptionEn']
                ?? $productData['description']
                ?? $productData['productDescription']
                ?? null
            );

            $attributes = [
                'cj_pid' => $pid,
                'cj_payload' => $productData,
                'cj_variants' => $variants,
            ];

            $rows[] = [
                'cj_pid' => $pid,
                'name' => $name,
                'slug' => $slug,
                'category_id' => $this->resolveCategoryFromPayload($productData)?->id ?? null,
                'description' => $incomingDescription,
                'selling_price' => $priceValue ?? 0,
                'cost_price' => $priceValue ?? 0,
                'currency' => $productData['currency'] ?? 'USD',
                'attributes' => json_encode($attributes, JSON_UNESCAPED_SLASHES),
                'source_url' => $productData['productUrl'] ?? $productData['sourceUrl'] ?? null,
                'cj_synced_at' => $now,
                'default_fulfillment_provider_id' => 1,
                'cj_last_payload' => json_encode($productData, JSON_UNESCAPED_SLASHES),
                'cj_last_changed_fields' => json_encode(['created']),
                'status' => 'draft',
                'is_active' => false,
                'is_featured' => false,
                'cj_sync_enabled' => ($options['defaultSyncEnabled'] ?? true) ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($rows)) {
            return ['created' => 0, 'updated' => 0, 'processed' => 0];
        }

        // Use upsert to insert or update by cj_pid in a single query
        $updateColumns = [
            'name', 'slug', 'category_id', 'description', 'selling_price', 'cost_price', 'currency', 'attributes', 'source_url', 'cj_synced_at', 'default_fulfillment_provider_id', 'cj_last_payload', 'cj_last_changed_fields', 'updated_at', 'status', 'is_active', 'is_featured', 'cj_sync_enabled'
        ];

        DB::transaction(function () use ($rows, $updateColumns) {
            // Chunk to a reasonable DB batch size to avoid giant queries
            $chunks = array_chunk($rows, 500);
            foreach ($chunks as $chunk) {
                Product::upsert($chunk, ['cj_pid'], $updateColumns);
            }
        });

        // Fetch product IDs for dispatched PIDs
        $productsMap = Product::query()->whereIn('cj_pid', $pids)->pluck('id', 'cj_pid')->toArray();

        $created = 0;
        $updated = 0;

        // Heuristic: if product's created_at == updated_at then it was created now; else updated.
        $nowTs = $now->toDateTimeString();
        foreach ($productsMap as $pid => $id) {
            $product = Product::query()->find($id);
            if (! $product) {
                continue;
            }
            if ($product->created_at && $product->created_at->toDateTimeString() === $nowTs) {
                $created++;
            } else {
                $updated++;
            }
        }

        // Optionally dispatch translation and SEO jobs in chunks
        $dispatchChunkSize = (int) ($options['dispatchChunkSize'] ?? 50);

        $productIds = array_values($productsMap);
        $translateLocales = $options['locales'] ?? $this->resolveTranslationLocales();
        $generateSeo = $options['generateSeo'] ?? true;
        $shouldTranslate = ($options['translate'] ?? true);

        if ($shouldTranslate && ! empty($productIds)) {
            $chunks = array_chunk($productIds, $dispatchChunkSize);
            foreach ($chunks as $chunk) {
                TranslateProductsChunkJob::dispatch($chunk, $translateLocales)->onQueue('translations');
            }
        }

        if ($generateSeo && ! empty($productIds)) {
            $chunks = array_chunk($productIds, $dispatchChunkSize);
            foreach ($chunks as $chunk) {
                GenerateProductSeoChunkJob::dispatch($chunk)->onQueue('seo');
            }
        }

        // Optionally dispatch media and variants sync in separate queues to
        // keep the import path fast and IO-bound work isolated.
        if (($options['syncMedia'] ?? false) && ! empty($productIds)) {
            $mediaChunk = (int) ($options['mediaChunkSize'] ?? max(10, (int)$dispatchChunkSize / 5));
            $chunks = array_chunk($productIds, $mediaChunk);
            foreach ($chunks as $chunk) {
                SyncProductMediaChunkJob::dispatch($chunk)->onQueue('media');
            }
        }

        if (($options['syncVariants'] ?? false) && ! empty($productIds)) {
            $variantsChunk = (int) ($options['variantsChunkSize'] ?? $dispatchChunkSize);
            $chunks = array_chunk($productIds, $variantsChunk);
            foreach ($chunks as $chunk) {
                SyncProductVariantsChunkJob::dispatch($chunk)->onQueue('variants');
            }
        }

        // Return product ids so callers can coordinate downstream jobs or release claims
        return ['created' => $created, 'updated' => $updated, 'processed' => count($productIds), 'product_ids' => $productIds];
    }

    /**
     * Sync media (images/videos) for a list of product IDs in bulk by delegating
     * to the existing `syncMedia` method. This keeps heavy I/O off the import
     * upsert path.
     *
     * @param int[] $productIds
     */
    public function syncMediaBulk(array $productIds): void
    {
        foreach ($productIds as $id) {
            try {
                $product = Product::find($id);
                if (! $product) {
                    continue;
                }

                $this->syncMedia($product, ['respectSyncFlag' => true, 'respectLocks' => true]);
            } catch (\Throwable $e) {
                Log::warning('Failed to sync media for product in bulk', ['product_id' => $id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Sync variants for a list of product IDs. Uses the private syncVariants
     * helper within this service.
     *
     * @param int[] $productIds
     */
    public function syncVariantsBulk(array $productIds): void
    {
        foreach ($productIds as $id) {
            try {
                $product = Product::find($id);
                if (! $product) {
                    continue;
                }

                $variants = null; // let syncVariants fetch variants if needed
                $this->syncVariants($product, $variants, $product->cj_pid ?? '');
            } catch (\Throwable $e) {
                Log::warning('Failed to sync variants for product in bulk', ['product_id' => $id, 'error' => $e->getMessage()]);
            }
        }
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
            $productOptionMap = [];

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

                    $rawSell = $variant['variantSellPrice'] ?? $variant['variantSugSellPrice'] ?? null;
                    $rawCost = is_numeric($rawSell) ? (float) $rawSell : ($product->cost_price ?? 0);
                    $sellPrice = is_numeric($rawSell) ? (float) $rawSell : ($product->selling_price ?? 0);

                    $title = $this->cleanVariantTitle(
                        $variant['variantName']
                            ?? $variant['variantNameEn']
                            ?? ($variant['variantKey'] ?? 'Variant'),
                        $product->name
                    );

                    $options = $this->parseVariantOptions($variant);
                    foreach ($options as $key => $value) {
                        $productOptionMap[$key][] = $value;
                    }

                    $variantLength = $this->parsePositiveInt($variant['variantLength'] ?? null);
                    $variantWidth = $this->parsePositiveInt($variant['variantWidth'] ?? null);
                    $variantHeight = $this->parsePositiveInt($variant['variantHeight'] ?? null);
                    $variantWeight = $this->parsePositiveInt($variant['variantWeight'] ?? null);

                    ProductVariant::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'cj_vid' => $vid ?: null,
                            'sku' => $sku,
                        ],
                        [
                            'title' => $title,
                            'price' => $this->applyMinMarginToPrice($sellPrice, $rawCost),
                            'cost_price' => $rawCost,
                            'currency' => $product->currency ?? 'USD',
                            'variant_image'=> $variant['variantImage'] ?? null,
                            'options' => $options === [] ? null : $options,
                            'weight_grams' => $variantWeight,
                            'package_length_mm' => $variantLength,
                            'package_width_mm' => $variantWidth,
                            'package_height_mm' => $variantHeight,
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

            if ($productOptionMap !== [] && (empty($product->options) || !is_array($product->options))) {
                $product->options = $this->formatProductOptions($productOptionMap);
                $product->save();
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
     * Normalize various CJ payload shapes (including My Products) into the
     * canonical keys expected by the importer. Keeps original keys intact.
     */
    private function normalizeProductPayload(array $productData): array
    {
        $data = $productData;

        $looksLikeMyProducts = isset($data['productId']) && (isset($data['nameEn']) || isset($data['sellPrice']) || isset($data['totalPrice']));

        if ($looksLikeMyProducts) {
            $pid = (string)($data['productId'] ?? '');
            if ($pid !== '') {
                $data['pid'] = $data['pid'] ?? $pid;
                $data['id'] = $data['id'] ?? $pid;
            }

            $name = $data['nameEn'] ?? $data['productName'] ?? $data['name'] ?? null;
            if ($name !== null) {
                $data['productNameEn'] = $data['productNameEn'] ?? $name;
                $data['productName'] = $data['productName'] ?? $name;
                $data['name'] = $data['name'] ?? $name;
            }

            $price = $data['productSellPrice'] ?? $data['sellPrice'] ?? $data['totalPrice'] ?? null;
            if ($price !== null) {
                $data['productSellPrice'] = $price;
            }

            if (!isset($data['currency'])) {
                $data['currency'] = 'USD';
            }

            // Map primary image
            if (isset($data['bigImage']) && !isset($data['productImage'])) {
                $data['productImage'] = $data['bigImage'];
            }
        }

        return $data;
    }

    private function cleanProductName(?string $name): string
    {
        if (! is_string($name)) {
            return '';
        }

        $clean = html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5);
        $clean = preg_replace('/\\s+/', ' ', $clean ?? '');
        // Remove trailing price/currency noise often appended in titles
        $clean = preg_replace('/\\s*[\\d\\.]+\\s?(USD|US\\$|\\$|FCFA|CFA)$/i', '', $clean ?? '');
        $clean = trim((string) $clean);

        return mb_substr($clean, 0, 190);
    }

    private function cleanDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }
        $clean = html_entity_decode((string) $description, ENT_QUOTES | ENT_HTML5);
        $clean = strip_tags($clean);
        $clean = preg_replace('/\\s+/', ' ', $clean ?? '');
        $clean = trim((string) $clean);

        return $clean === '' ? null : $clean;
    }

    private function cleanVariantTitle(?string $title, ?string $fallbackBase = null): string
    {
        $text = is_string($title) ? $title : '';
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\\s+/', ' ', $text ?? '');
        $text = trim((string) $text);

        if ($text === '' && $fallbackBase) {
            $text = trim($fallbackBase . ' Variant');
        }

        return mb_substr($text, 0, 190);
    }

    private function applyMinMarginToPrice(float $price, float $cost): float
    {
        $pricing = PricingService::makeFromConfig();
        $min = $pricing->minSellingPrice(max(0, $cost));
        return max($price, $min);
    }

    /**
     * Parse CJ variant property strings/arrays into a normalized option map.
     * Returns key => value pairs (e.g., Color => Red, Size => XL).
     */
    private function parseVariantOptions(array $variant): array
    {
        $options = [];

        // variantProperty may be JSON string or array like [{propertyName:"Color", propertyValue:"Red"}]
        $raw = $variant['variantProperty'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }

        if (is_array($raw)) {
            foreach ($raw as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $name = $entry['propertyName'] ?? $entry['name'] ?? null;
                $value = $entry['propertyValue'] ?? $entry['value'] ?? null;
                if (is_string($name) && $name !== '' && is_string($value) && $value !== '') {
                    $options[trim($name)] = trim($value);
                }
            }
        }

        // variantKey sometimes contains a single option string
        if (($variant['variantKey'] ?? '') !== '' && $options === []) {
            $options['Option'] = trim((string) $variant['variantKey']);
        }

        return $options;
    }

    private function formatProductOptions(array $optionMap): array
    {
        $formatted = [];
        foreach ($optionMap as $name => $values) {
            $vals = array_values(array_unique(array_filter(array_map('strval', $values))));
            if ($vals === []) {
                continue;
            }
            $formatted[] = [
                'name' => $name,
                'values' => $vals,
            ];
        }

        return $formatted;
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) round((float) $value);
        return $int > 0 ? $int : null;
    }

    private function isRemovedFromShelves(ApiException $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'removed from shelves')
            || str_contains($message, 'off shelf')
            || str_contains($message, 'offline')
            || in_array($e->codeString, ['PRODUCT_OFF_SHELF', '404'], true);
    }

    private function markProductRemoved(string $pid, ?string $reason = null): void
    {
        $payload = [
            'status' => 'draft',
            'is_active' => false,
            'cj_sync_enabled' => false,
            'cj_synced_at' => now(),
        ];

        Product::query()->where('cj_pid', $pid)->update($payload);
        Log::warning('CJ product marked as removed', ['pid' => $pid, 'reason' => $reason]);
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
