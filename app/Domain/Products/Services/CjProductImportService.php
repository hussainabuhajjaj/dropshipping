<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Products\Models\Category;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Jobs\GenerateProductCompareAtJob;
use App\Jobs\GenerateProductSeoJob;
use App\Jobs\TranslateProductJob;
use App\Jobs\TranslateProductsChunkJob;
use App\Jobs\GenerateProductSeoChunkJob;
use App\Jobs\SyncProductMediaChunkJob;
use App\Jobs\SyncProductVariantsChunkJob;
use App\Jobs\SyncCjStockByVidChunkJob;
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
        private readonly ?CjCategoryResolver $categoryResolver = null,
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
            // First try to use variants from product data (which may include inventory data)
            if (isset($productData['variants']) && is_array($productData['variants'])) {
                $variants = $productData['variants'];
                Log::info('Using variants from product data', ['pid' => $pid, 'variant_count' => count($variants)]);
            } else {
                // Fallback to fetching variants via API
                try {
                    $variantResp = $this->client->getVariantsByPid($pid);
                    $variants = $variantResp->data ?? [];
                    Log::info('Fetched variants via API', ['pid' => $pid, 'variant_count' => count($variants)]);
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
        }

        // Ensure variants carry inventory information (adds inventories[] via stock API when missing)
        if (is_array($variants) && $variants !== []) {
            $variants = $this->attachInventoriesToVariants($variants);
        }

        $category = $this->resolveCategoryFromPayload($productData);

        $rawName = $productData['productNameEn'] ?? $productData['productName'] ?? ($productData['name'] ?? null);
        $name = $this->cleanProductName($rawName) ?: 'CJ Product ' . $pid;
        $slug = Str::slug($name . '-' . $pid);
        // NOTE: Selling price NOT extracted from CJ API - using margin-based pricing only
        $priceValue = null; // No CJ API selling price
        $rawCost = $productData['productCost'] ?? 0; // Only extract cost price
        if ((!is_numeric($rawCost) || (float) $rawCost <= 0) && isset($productData['productSellPrice']) && is_numeric($productData['productSellPrice'])) {
            $rawCost = (float) $productData['productSellPrice'];
        }

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

        // Validate currency first (needed for pricing calculation)
        $currency = $productData['currency'] ?? 'USD';
        if (!in_array($currency, ['USD'])) {
            Log::warning('Unsupported currency detected, defaulting to USD', [
                'cj_pid' => $pid,
                'currency' => $currency
            ]);
            $currency = 'USD';
        }

        // NOTE: ONLY extract cost price from CJ API - everything else from margin pricing
        $rawCost = $productData['productCost'] ?? 0; // Only cost price from CJ API
        if ((!is_numeric($rawCost) || (float) $rawCost <= 0) && isset($productData['productSellPrice']) && is_numeric($productData['productSellPrice'])) {
            $rawCost = (float) $productData['productSellPrice'];
        }

        // Validate cost price
        if (!is_numeric($rawCost) || $rawCost < 0) {
            Log::warning('Invalid cost price detected, using default', [
                'cj_pid' => $pid,
                'raw_cost' => $rawCost
            ]);
            $rawCost = 0;
        }

        // Extract stock information from CJ API data
        $totalStock = (int) ($productData['totalStock'] ?? $productData['stock'] ?? 0);
        $stockOnHand = $this->calculateStockOnHand($totalStock);

        $payload = [
            'name' => $name,
            'category_id' => $category?->id,
            'description' => $description,
            'selling_price' => $product?->selling_price ?? null, // Keep existing, don't update
            'cost_price' => $rawCost, // ONLY cost price from CJ API
            'currency' => $productData['currency'] ?? 'USD',
            'attributes' => $attributes,
            'source_url' => $productData['productUrl'] ?? $productData['sourceUrl'] ?? null,
            'cj_synced_at' => now(),
            'cj_removed_from_shelves_at' => null,
            'cj_removed_reason' => null,
            'stock_on_hand' => $stockOnHand,
            'cj_total_stock' => $totalStock, // Store total CJ stock for reference
            'default_fulfillment_provider_id' => $this->resolveDefaultFulfillmentProviderId(),
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
                'stock_on_hand' => $payload['stock_on_hand'],
                'cj_total_stock' => $payload['cj_total_stock'],
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

            // Set import tracking for new products
            $payload['cj_imported_at'] = now();
            $payload['cj_import_batch_id'] = $options['import_batch_id'] ?? null;

            $product = Product::create($payload);
        } else {
            // Only set import tracking if it's not already set (first time import)
            if (!$product->cj_imported_at) {
                $payload['cj_imported_at'] = now();
                $payload['cj_import_batch_id'] = $options['import_batch_id'] ?? null;
            }

            // Preserve original created_at timestamp for existing products
            unset($payload['created_at']);

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

    private function resolveDefaultFulfillmentProviderId(): ?int
    {
        static $resolved = false;
        static $resolvedId = null;

        if ($resolved) {
            return $resolvedId;
        }

        $resolved = true;

        $configuredId = (int) config('services.cj.default_fulfillment_provider_id', 1);

        if ($configuredId > 0) {
            $exists = FulfillmentProvider::query()->whereKey($configuredId)->exists();
            if ($exists) {
                $resolvedId = $configuredId;
                return $resolvedId;
            }

            Log::warning('Configured default fulfillment provider was not found; falling back.', [
                'configured_provider_id' => $configuredId,
            ]);
        }

        $fallbackId = FulfillmentProvider::query()->orderBy('id')->value('id');
        if ($fallbackId !== null) {
            $resolvedId = (int) $fallbackId;
            return $resolvedId;
        }

        Log::warning('No fulfillment provider found; default provider will be null for imported products.');

        $resolvedId = null;
        return $resolvedId;
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
            dd($price);
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

            // After variants are synced/created, schedule a stock refresh by VID.
            // This uses CJ queryByVid and maps stock_on_hand to totalInventoryNum.
            // Note: this assumes the variants sync job runs quickly; even if it lags,
            // running this periodically will converge.
            try {
                $vids = \App\Domain\Products\Models\ProductVariant::query()
                    ->whereIn('product_id', $productIds)
                    ->whereNotNull('cj_vid')
                    ->where('cj_vid', '!=', '')
                    ->pluck('cj_vid')
                    ->map(fn ($v) => (string) $v)
                    ->filter()
                    ->values()
                    ->all();

                foreach (array_chunk($vids, 40) as $vidChunk) {
                    SyncCjStockByVidChunkJob::dispatch($vidChunk)->onQueue((string) config('cj.stock_queue', 'cj-sync'));
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch CJ stock sync after import', ['error' => $e->getMessage()]);
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
                    ])->onQueue('cj-import');
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
        // CRITICAL FIX: Validate product relationship before processing variants
        if (!$product->exists || !$product->cj_pid || $product->cj_pid !== $pid) {
            Log::error('Product-Variant relationship validation failed', [
                'cj_pid' => $pid,
                'product_id' => $product->id,
                'product_cj_pid' => $product->cj_pid,
                'product_exists' => $product->exists,
                'error' => 'Product relationship mismatch detected'
            ]);
            throw new \RuntimeException('Product relationship validation failed');
        }

        if (is_array($variants) && $variants !== []) {
            $productOptionMap = [];
            $processedVids = []; // Track processed VIDs to prevent duplicates

            foreach ($variants as $variant) {
                try {
                    if (!is_array($variant)) {
                        continue;
                    }

                    $vid = (string)($variant['vid'] ?? '');
                    $sku = $variant['variantSku'] ?? $variant['sku'] ?? null;

                    // CRITICAL FIX: Skip duplicate variants within same batch
                    if ($vid && isset($processedVids[$vid])) {
                        Log::warning('Duplicate variant detected in batch, skipping', [
                            'cj_pid' => $pid,
                            'cj_vid' => $vid,
                            'sku' => $sku
                        ]);
                        continue;
                    }
                    $processedVids[$vid] = true;

                    if (!$sku && !$vid) {
                        continue;
                    }

                    // NOTE: ONLY extract cost price from CJ API - everything else from margin pricing
                    $rawCost = $variant['variantCost'] ?? $product->cost_price ?? 0; // Only cost price from CJ API
                    if ((!is_numeric($rawCost) || (float) $rawCost <= 0) && isset($variant['variantSellPrice']) && is_numeric($variant['variantSellPrice'])) {
                        $rawCost = (float) $variant['variantSellPrice'];
                    }

                    // Validate cost price
                    if (!is_numeric($rawCost) || $rawCost < 0) {
                        Log::warning('Invalid variant cost price detected, using product cost', [
                            'cj_pid' => $pid,
                            'cj_vid' => $vid,
                            'raw_cost' => $rawCost
                        ]);
                        $rawCost = $product->cost_price ?? 0;
                    }

                    // Derive sell price from CJ data when available; fallback to cost
                    $sellPrice = null;
                    if (isset($variant['variantSellPrice']) && is_numeric($variant['variantSellPrice'])) {
                        $sellPrice = (float) $variant['variantSellPrice'];
                    } elseif (isset($variant['variantSugSellPrice']) && is_numeric($variant['variantSugSellPrice'])) {
                        $sellPrice = (float) $variant['variantSugSellPrice'];
                    } elseif (is_numeric($rawCost) && (float) $rawCost > 0) {
                        $sellPrice = (float) $rawCost;
                    }

                    // Ensure numeric types for typed variant creation
                    $rawCost = (float) $rawCost;
                    $sellPrice = is_numeric($sellPrice) ? (float) $sellPrice : (float) $rawCost;

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

                    // CRITICAL FIX: Standardized inventory data extraction with priority validation
                    $stockData = $this->extractVariantStockWithValidation($variant, $vid, $pid);
                    $variantStock = $stockData['cj_stock'];
                    $variantStockOnHand = $stockData['stock_on_hand'];

                    // CRITICAL FIX: Atomic variant creation with enhanced validation
                    $this->createVariantWithValidation($product, $vid, $sku, $title, $sellPrice, $rawCost, $options, $variant, $variantStock, $variantStockOnHand, $pid);
                } catch (\Throwable $e) {
                    Log::warning('Failed to sync single variant', ['product_id' => $product->id, 'variant' => $variant, 'error' => $e->getMessage()]);
                }
            }

            if ($productOptionMap !== [] && (empty($product->options) || !is_array($product->options))) {
                $product->options = $this->formatProductOptions($productOptionMap);
                $product->save();
            }

            // Log successful variant import
            Log::info('Product variants imported successfully', [
                'cj_pid' => $pid,
                'product_id' => $product->id,
                'variants_imported' => $product->variants()->count(),
                'action' => 'VARIANTS_IMPORTED_SUCCESSFULLY',
            ]);

            return;
        }

        // NEW LOGIC: Handle import failures and products without variants
        if (!$product->variants()->exists()) {
            // Check if we had variant data but import failed
            if (isset($productData['variants']) && !empty($productData['variants'])) {
                Log::warning('Product import failed - variant data exists but no variants created', [
                    'cj_pid' => $pid,
                    'product_id' => $product->id,
                    'variant_count' => count($productData['variants']),
                    'action' => 'IMPORT_FAILURE_NO_DEFAULT_VARIANT',
                ]);
            } else {
                // Product naturally has no variants (no variant data from CJ)
                Log::info('Product has no variants - keeping product-only', [
                    'cj_pid' => $pid,
                    'product_id' => $product->id,
                    'action' => 'PRODUCT_ONLY_NO_VARIANTS',
                ]);
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

            // Map product cost from sellPrice when explicit cost is missing
            if (!isset($data['productCost']) && $price !== null && is_numeric($price)) {
                $data['productCost'] = (float) $price;
            }

            if (!isset($data['currency'])) {
                $data['currency'] = 'USD';
            }

            // Map primary image
            if (isset($data['bigImage']) && !isset($data['productImage'])) {
                $data['productImage'] = $data['bigImage'];
            }
        }

        // Generic mapping: if productCost missing but sellPrice/productSellPrice present, set productCost
        if (!isset($data['productCost'])) {
            $possibleCost = $data['productSellPrice'] ?? $data['sellPrice'] ?? null;
            if (is_numeric($possibleCost)) {
                $data['productCost'] = (float) $possibleCost;
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

    private function applyMinMarginToPrice(float $price, float $cost, string $currency = 'USD'): float
    {
        $pricing = PricingService::makeFromConfig();
        $min = $pricing->minSellingPrice(max(0, $cost), $currency);
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
            'cj_removed_from_shelves_at' => now(),
            'cj_removed_reason' => $reason ? Str::limit($reason, 500) : 'Removed from shelves',
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
        return $this->categoryResolver()->resolveFromPayload($productData, createMissing: true);
    }

    private function categoryResolver(): CjCategoryResolver
    {
        return $this->categoryResolver ?? app(CjCategoryResolver::class);
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

    /**
     * Import products from CJ My Products with full pipeline:
     * enrichment, margin application, validation, and activation.
     *
     * @param array{
     *   pids?: array<string>,
     *   margin_percent?: float,
     *   enrich?: bool,
     *   enrich_sleep_ms?: int,
     *   skip_existing?: bool,
     *   skip_translations?: bool,
     *   skip_seo?: bool,
     *   locales?: array<string>,
     *   limit?: int,
     *   chunk_size?: int,
     *   dry_run?: bool,
     *   force_activate?: bool
     * } $options
     * @return array{
     *   fetched: int,
     *   enriched: int,
     *   imported: int,
     *   priced: int,
     *   media_synced: int,
     *   variants_synced: int,
     *   activated: int,
     *   failed_activation: int,
     *   activation_errors: array<string, array<string>>,
     *   removed: int,
     *   translations_queued: int,
     *   seo_queued: int
     * }
     */
    public function importBulkWithPipeline(array $options = []): array
    {
        $marginPercent = (float) ($options['margin_percent'] ?? config('services.cj.import_margin', 35));
        $enrich = (bool) ($options['enrich'] ?? config('services.cj.import_enrich', true));
        $enrichSleepMs = (int) ($options['enrich_sleep_ms'] ?? config('services.cj.import_enrich_sleep_ms', 200));
        $skipExisting = (bool) ($options['skip_existing'] ?? false);
        $skipTranslations = (bool) ($options['skip_translations'] ?? false);
        $skipSeo = (bool) ($options['skip_seo'] ?? false);
        $locales = $options['locales'] ?? $this->resolveTranslationLocales();
        $limit = isset($options['limit']) ? (int) $options['limit'] : null;
        $chunkSize = (int) ($options['chunk_size'] ?? config('services.cj.import_chunk_size', 25));
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $forceActivate = (bool) ($options['force_activate'] ?? false);
        $specificPids = $options['pids'] ?? null;

        $report = [
            'fetched' => 0,
            'enriched' => 0,
            'imported' => 0,
            'priced' => 0,
            'media_synced' => 0,
            'variants_synced' => 0,
            'activated' => 0,
            'failed_activation' => 0,
            'activation_errors' => [],
            'removed' => 0,
            'translations_queued' => 0,
            'seo_queued' => 0,
        ];
        $processed = 0;

        $validator = app(ProductActivationValidator::class);
        $pricing = PricingService::makeFromConfig();

        // Defensive reset: this counter is used in both specific-PID and paginated flows.
        $processed = (int) $processed;

        // If specific PIDs provided, import them directly (catalog import)
        if ($specificPids !== null && !empty($specificPids)) {
            foreach ($specificPids as $pid) {
                if ($limit && $processed >= $limit) {
                    break;
                }

                try {
                    // Fetch product directly by PID
                    $detailResp = $this->client->getProduct($pid);
                    if (!isset($detailResp->data) || !is_array($detailResp->data)) {
                        continue;
                    }

                    $fullData = $detailResp->data;
                    $report['fetched']++;

                    // Fetch variants
                    $variants = [];
                    if ($enrich) {
                        try {
                            $variantResp = $this->client->getVariantsByPid($pid);
                            $variants = $variantResp->data ?? [];
                            $report['enriched']++;

                            if ($enrichSleepMs > 0) {
                                usleep($enrichSleepMs * 1000);
                            }
                        } catch (ApiException $e) {
                            Log::warning('Variant fetch failed for PID', ['pid' => $pid, 'error' => $e->getMessage()]);
                        }
                    }

                    if ($dryRun) {
                        $processed++;
                        continue;
                    }

                    // Import product
                    $this->processProductImport($fullData, $variants, $pid, $marginPercent, $forceActivate, $skipTranslations, $skipSeo, $locales, $validator, $report);
                    $processed++;

                } catch (ApiException $e) {
                    if ($this->isRemovedFromShelves($e)) {
                        if (!$dryRun) {
                            $this->markProductRemoved($pid, $e->getMessage());
                        }
                        $report['removed']++;
                    } else {
                        Log::error('Direct PID import failed', ['pid' => $pid, 'error' => $e->getMessage()]);
                    }
                } catch (\Throwable $e) {
                    Log::error('Pipeline import failed for PID', [
                        'pid' => $pid,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            return $report;
        }

        // Fetch products from CJ My Products (paginated)
        $page = 1;

        while (true) {
            if ($limit && $processed >= $limit) {
                break;
            }

            try {
                $resp = $this->client->listMyProducts([
                    'pageNum' => $page,
                    'pageSize' => $chunkSize,
                ]);

                $data = $resp->data ?? [];
                $products = $data['list'] ?? [];

                if (empty($products)) {
                    break;
                }

                $report['fetched'] += count($products);

                foreach ($products as $productData) {
                    if ($limit && $processed >= $limit) {
                        break 2;
                    }

                    $pid = $this->resolvePid($productData);
                    if ($pid === '') {
                        continue;
                    }

                    // Skip existing if requested
                    if ($skipExisting) {
                        $exists = Product::query()->where('cj_pid', $pid)->exists();
                        if ($exists) {
                            continue;
                        }
                    }

                    // Enrich: fetch full product details
                    $fullData = $productData;
                    $variants = [];

                    if ($enrich) {
                        try {
                            $detailResp = $this->client->getProduct($pid);
                            if (isset($detailResp->data) && is_array($detailResp->data)) {
                                $fullData = array_merge($productData, $detailResp->data);
                                $report['enriched']++;
                            }

                            $variantResp = $this->client->getVariantsByPid($pid);
                            $variants = $variantResp->data ?? [];

                            if ($enrichSleepMs > 0) {
                                usleep($enrichSleepMs * 1000);
                            }
                        } catch (ApiException $e) {
                            if ($this->isRemovedFromShelves($e)) {
                                if (!$dryRun) {
                                    $this->markProductRemoved($pid, $e->getMessage());
                                }
                                $report['removed']++;
                                continue;
                            }
                            Log::warning('Enrichment failed for PID', ['pid' => $pid, 'error' => $e->getMessage()]);
                        }
                    }

                    if ($dryRun) {
                        $processed++;
                        continue;
                    }

                    // Import product with inline processing
                    try {
                        $this->processProductImport($fullData, $variants, $pid, $marginPercent, $forceActivate, $skipTranslations, $skipSeo, $locales, $validator, $report);
                        $processed++;
                    } catch (\Throwable $e) {
                        Log::error('Pipeline import failed for PID', [
                            'pid' => $pid,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }

                $page++;
            } catch (ApiException $e) {
                Log::error('CJ My Products API failed', [
                    'page' => $page,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        }

        return $report;
    }

    /**
     * Process a single product import with margin, validation, and activation.
     */
    private function processProductImport(
        array $fullData,
        array $variants,
        string $pid,
        float $marginPercent,
        bool $forceActivate,
        bool $skipTranslations,
        bool $skipSeo,
        array $locales,
        $validator,
        array &$report
    ): void {
        $product = $this->importFromPayload($fullData, $variants, [
            'syncVariants' => true,
            'syncImages' => true,
            'translate' => false,
            'generateSeo' => false,
            'respectSyncFlag' => false,
            'updateExisting' => true,
        ]);

        if (!$product) {
            return;
        }

        $report['imported']++;

        // Sync real-time stock from CJ API with enhanced method
        $this->syncProductStockEnhanced($product, [
            'batch_size' => 10,
            'api_delay_ms' => 150,
            'max_retries' => 3,
            'use_cache' => true,
        ]);

        // Apply margin
        $costPrice = (float) ($product->cost_price ?? 0);
        if ($costPrice > 0) {
            $marginFactor = 1 + ($marginPercent / 100);
            $sellingPrice = round($costPrice * $marginFactor, 2);
            $product->selling_price = $sellingPrice;
            $product->save();
            $report['priced']++;

            // Apply margin to variants
            foreach ($product->variants as $variant) {
                $variantCost = (float) ($variant->cost_price ?? $costPrice);
                if ($variantCost > 0) {
                    $variant->price = round($variantCost * $marginFactor, 2);
                    $variant->save();
                }
            }
        }

        // Count media/variants sync
        if ($product->images()->count() > 0) {
            $report['media_synced']++;
        }
        if ($product->variants()->count() > 0) {
            $report['variants_synced']++;
        }

        // Validate and activate
        $errors = $validator->errorsForActivation($product);
        if (empty($errors) || $forceActivate) {
            $product->update([
                'is_active' => true,
                'status' => 'active',
            ]);
            $report['activated']++;
        } else {
            $report['failed_activation']++;
            $report['activation_errors'][$pid] = $errors;
        }

        // Queue translations
        if (!$skipTranslations && !empty($locales)) {
            TranslateProductJob::dispatch(
                (int) $product->id,
                $locales,
                $this->resolveTranslationSourceLocale(),
                false
            )->onQueue('translations');
            $report['translations_queued']++;
        }

        // Queue SEO
        if (!$skipSeo) {
            GenerateProductSeoJob::dispatch((int) $product->id, 'en', false)->onQueue('seo');
            $report['seo_queued']++;
        }
    }

    /**
     * Enhanced stock sync with batch processing, retry logic, and better error handling
     */
    private function syncProductStockEnhanced(Product $product, array $options = []): void
    {
        $variants = $product->variants()->whereNotNull('cj_vid')->get();
        if ($variants->isEmpty()) {
            return;
        }

        $batchSize = $options['batch_size'] ?? 10;
        $apiDelay = $options['api_delay_ms'] ?? 150;
        $maxRetries = $options['max_retries'] ?? 3;
        $useCache = $options['use_cache'] ?? true;

        $vids = $variants->pluck('cj_vid')->filter()->all();
        $chunks = array_chunk($vids, $batchSize);

        Log::info('Starting enhanced stock sync', [
            'product_id' => $product->id,
            'total_variants' => count($vids),
            'batch_size' => $batchSize,
            'total_batches' => count($chunks),
        ]);

        $totalProductStock = 0;
        $syncResults = ['updated' => 0, 'errors' => 0, 'skipped' => 0];

        foreach ($chunks as $chunkIndex => $vidChunk) {
            $chunkResults = $this->syncVidChunk($vidChunk, [
                'chunk_index' => $chunkIndex,
                'api_delay_ms' => $apiDelay,
                'max_retries' => $maxRetries,
                'use_cache' => $useCache,
            ]);

            $totalProductStock += $chunkResults['total_stock'];
            $syncResults['updated'] += $chunkResults['updated'];
            $syncResults['errors'] += $chunkResults['errors'];
            $syncResults['skipped'] += $chunkResults['skipped'];

            // Delay between chunks to avoid rate limiting
            if ($chunkIndex < count($chunks) - 1 && $apiDelay > 0) {
                usleep($apiDelay * 1000);
            }
        }

        // Update product-level stock
        $product->update([
            'cj_total_stock' => $totalProductStock,
            'stock_on_hand' => $this->calculateStockOnHand($totalProductStock),
        ]);

        Log::info('Enhanced stock sync completed', [
            'product_id' => $product->id,
            'total_stock' => $totalProductStock,
            'updated' => $syncResults['updated'],
            'errors' => $syncResults['errors'],
            'skipped' => $syncResults['skipped'],
        ]);
    }

    /**
     * Sync a chunk of VIDs with enhanced error handling and retry logic
     */
    private function syncVidChunk(array $vidChunk, array $options): array
    {
        $chunkIndex = $options['chunk_index'];
        $apiDelay = $options['api_delay_ms'];
        $maxRetries = $options['max_retries'];
        $useCache = $options['use_cache'];

        $results = [
            'updated' => 0,
            'errors' => 0,
            'skipped' => 0,
            'total_stock' => 0,
        ];

        foreach ($vidChunk as $vid) {
            try {
                $result = $this->syncSingleVariantEnhanced($vid, [
                    'max_retries' => $maxRetries,
                    'use_cache' => $useCache,
                ]);

                if ($result['updated']) {
                    $results['updated']++;
                    $results['total_stock'] += $result['stock'];
                } elseif ($result['skipped']) {
                    $results['skipped']++;
                } else {
                    $results['errors']++;
                }

                // Rate limiting between individual API calls
                if ($apiDelay > 0) {
                    usleep($apiDelay * 1000);
                }

            } catch (\Exception $e) {
                $results['errors']++;
                Log::error('Critical error syncing variant', [
                    'vid' => $vid,
                    'chunk_index' => $chunkIndex,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Enhanced single variant sync with retry logic and validation
     */
    private function syncSingleVariantEnhanced(string $vid, array $options): array
    {
        $maxRetries = $options['max_retries'];
        $useCache = $options['use_cache'];

        // Check cache first
        if ($useCache) {
            $cacheKey = "cj_stock_{$vid}";
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('Using cached stock data', ['vid' => $vid]);
                return $this->updateVariantFromCachedData($vid, $cached);
            }
        }

        // Fetch with retry logic
        $stockData = $this->fetchStockWithRetry($vid, $maxRetries);
        if (!$stockData) {
            return ['updated' => false, 'skipped' => false, 'stock' => 0];
        }

        // Calculate stock
        $totalStock = $this->calculateTotalStockFromData($stockData);
        $stockOnHand = $this->calculateStockOnHand($totalStock);

        // Update variant
        $updated = $this->updateVariantStock($vid, $totalStock, $stockOnHand);

        // Cache the result
        if ($useCache && $updated) {
            Cache::put("cj_stock_{$vid}", $stockData, 300); // 5 minutes
        }

        return ['updated' => $updated, 'skipped' => !$updated, 'stock' => $totalStock];
    }

    /**
     * Fetch stock data with retry logic and exponential backoff
     */
    private function fetchStockWithRetry(string $vid, int $maxRetries = 3): ?array
    {
        $attempt = 0;
        $maxDelay = 5000; // 5 seconds max delay

        while ($attempt < $maxRetries) {
            try {
                $response = $this->client->getStockByVid($vid);
                $data = $response->data ?? null;

                if (is_array($data) && !empty($data)) {
                    return $data;
                }

                Log::warning('Invalid stock response', [
                    'vid' => $vid,
                    'attempt' => $attempt + 1,
                    'data_type' => gettype($data),
                ]);

            } catch (ApiException $e) {
                Log::warning('API error fetching stock', [
                    'vid' => $vid,
                    'attempt' => $attempt + 1,
                    'status' => $e->status,
                    'error' => $e->getMessage(),
                ]);

                // Don't retry on client errors (4xx)
                if ($e->status >= 400 && $e->status < 500) {
                    return null;
                }

            } catch (\Exception $e) {
                Log::warning('Unexpected error fetching stock', [
                    'vid' => $vid,
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);
            }

            $attempt++;
            if ($attempt < $maxRetries) {
                // Exponential backoff: 100ms, 400ms, 1600ms
                $delay = min(100 * pow(4, $attempt - 1), $maxDelay);
                usleep($delay * 1000);
            }
        }

        return null;
    }

    /**
     * Calculate total stock from CJ API response data
     */
    private function calculateTotalStockFromData(array $stockData): int
    {
        $totalStock = 0;

        foreach ($stockData as $warehouseStock) {
            if (is_array($warehouseStock)) {
                $stock = $warehouseStock['totalInventoryNum'] ??
                         $warehouseStock['storageNum'] ??
                         $warehouseStock['cjInventoryNum'] ??
                         $warehouseStock['inventory'] ?? 0;
                $totalStock += (int) $stock;
            }
        }

        return $totalStock;
    }

    /**
     * Update variant stock with validation
     */
    private function updateVariantStock(string $vid, int $totalStock, int $stockOnHand): bool
    {
        try {
            $variant = ProductVariant::where('cj_vid', $vid)->first();

            if (!$variant) {
                Log::warning('Variant not found for stock update', ['vid' => $vid]);
                return false;
            }

            // Validate stock values
            if ($totalStock < 0 || $stockOnHand < 0) {
                Log::warning('Invalid stock values', [
                    'vid' => $vid,
                    'total_stock' => $totalStock,
                    'stock_on_hand' => $stockOnHand,
                ]);
                return false;
            }

            // Check if update is needed
            if ($variant->cj_stock === $totalStock && $variant->stock_on_hand === $stockOnHand) {
                Log::debug('Stock unchanged, skipping update', [
                    'vid' => $vid,
                    'cj_stock' => $totalStock,
                ]);
                return true; // Consider this successful
            }

            $oldStock = $variant->cj_stock;
            $oldStockOnHand = $variant->stock_on_hand;

            $variant->update([
                'cj_stock' => $totalStock,
                'stock_on_hand' => $stockOnHand,
                'cj_stock_synced_at' => now(),
            ]);

            Log::debug('Variant stock updated', [
                'vid' => $vid,
                'old_cj_stock' => $oldStock,
                'new_cj_stock' => $totalStock,
                'old_stock_on_hand' => $oldStockOnHand,
                'new_stock_on_hand' => $stockOnHand,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Database error updating variant stock', [
                'vid' => $vid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update variant from cached data
     */
    private function updateVariantFromCachedData(string $vid, array $stockData): array
    {
        $totalStock = $this->calculateTotalStockFromData($stockData);
        $stockOnHand = $this->calculateStockOnHand($totalStock);
        $updated = $this->updateVariantStock($vid, $totalStock, $stockOnHand);

        return ['updated' => $updated, 'skipped' => !$updated, 'stock' => $totalStock];
    }

    /**
     * Calculate stock_on_hand with configurable percentage
     */
    private function calculateStockOnHand(int $totalStock): int
    {
        if ($totalStock <= 0) {
            return 0;
        }

        // Get configurable stock percentage (default 75% instead of 50%)
        $percentage = (float) config('services.cj.stock_percentage', 75.0);

        // Ensure percentage is between 10% and 100%
        $percentage = max(10.0, min(100.0, $percentage));

        $stockOnHand = (int) ($totalStock * ($percentage / 100.0));

        Log::debug('Stock calculation', [
            'total_stock' => $totalStock,
            'percentage' => $percentage,
            'stock_on_hand' => $stockOnHand,
        ]);

        return $stockOnHand;
    }

    /**
     * Legacy stock sync method (kept for backward compatibility)
     */
    private function syncProductStock(Product $product): void
    {
        try {
            $variants = $product->variants;
            if ($variants->isEmpty()) {
                return;
            }

            $totalProductStock = 0;

            foreach ($variants as $variant) {
                $vid = $variant->cj_vid;
                if (!$vid) {
                    continue;
                }

                try {
                    // Fetch real-time stock from CJ API
                    $stockResponse = $this->client->getStockByVid($vid);
                    $stockData = $stockResponse->data ?? [];

                    $variantTotalStock = 0;

                    // Parse response according to CJ API docs
                    // data is an array of warehouse stock info
                    foreach ($stockData as $warehouseStock) {
                        // totalInventoryNum is the total available stock
                        $stock = $warehouseStock['totalInventoryNum'] ??
                            $warehouseStock['storageNum'] ??
                            $warehouseStock['cjInventoryNum'] ?? 0;
                        $variantTotalStock += (int) $stock;
                    }

                    // Update variant stock
                    $variant->update([
                        'cj_stock' => $variantTotalStock,
                        'stock_on_hand' => $variantTotalStock > 0 ? (int) ($variantTotalStock / 2) : 0,
                        'cj_stock_synced_at' => now(),
                    ]);

                    $totalProductStock += $variantTotalStock;

                    Log::debug('Synced stock for variant', [
                        'vid' => $vid,
                        'stock' => $variantTotalStock,
                    ]);

                } catch (\Exception $e) {
                    Log::warning('Failed to sync stock for variant', [
                        'vid' => $vid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update product-level stock
            $product->update([
                'cj_total_stock' => $totalProductStock,
                'stock_on_hand' => $totalProductStock > 0 ? (int) ($totalProductStock / 2) : 0,
            ]);

            Log::info('Synced product stock', [
                'product_id' => $product->id,
                'total_stock' => $totalProductStock,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to sync product stock', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * CRITICAL FIX: Enhanced product-level price validation with corruption prevention
     */
    private function validateAndCalculateProductPrice(?float $priceValue, float $rawCost, ?Product $product, string $pid): float
    {
        $pricing = PricingService::makeFromConfig();
        $currency = $product->currency ?? 'USD';

        // Initialize with safe defaults
        $sellingPrice = $priceValue ?? $product->selling_price ?? 0;

        // Validate basic numeric constraints
        if (!is_numeric($sellingPrice) || $sellingPrice < 0) {
            $minSell = $pricing->minSellingPrice($rawCost, $currency);
            $sellingPrice = $minSell;

            Log::warning('Invalid product price, using minimum', [
                'cj_pid' => $pid,
                'raw_cost' => $rawCost,
                'price_value' => $priceValue,
                'corrected_price' => $sellingPrice
            ]);
        }

        // CRITICAL FIX: Enhanced corruption detection with multiple validation layers
        $maxAllowedMarkup = (float) config('services.cj.max_markup_multiplier', 15.0);
        $corruptionThreshold = $rawCost * $maxAllowedMarkup;

        if ($sellingPrice > $corruptionThreshold) {
            Log::error('CRITICAL: Extreme product price corruption detected', [
                'cj_pid' => $pid,
                'raw_cost' => $rawCost,
                'corrupted_price' => $sellingPrice,
                'corruption_threshold' => $corruptionThreshold,
                'markup_multiplier' => $sellingPrice / max($rawCost, 0.01)
            ]);

            // Force minimum price for corruption
            $minSell = $pricing->minSellingPrice($rawCost, $currency);
            return $minSell;
        }

        // Additional sanity check for reasonable price ranges
        $reasonableMarkup = (float) config('services.cj.reasonable_markup_multiplier', 10.0);
        $maxReasonablePrice = $rawCost * $reasonableMarkup;

        if ($sellingPrice > $maxReasonablePrice) {
            Log::warning('Unreasonable product price detected, applying maximum reasonable price', [
                'cj_pid' => $pid,
                'raw_cost' => $rawCost,
                'selling_price' => $sellingPrice,
                'max_reasonable' => $maxReasonablePrice,
                'markup_multiplier' => $sellingPrice / max($rawCost, 0.01)
            ]);
            $sellingPrice = $maxReasonablePrice;
        }

        // Final validation: ensure price covers costs with minimum margin
        $minSell = $pricing->minSellingPrice($rawCost, $currency);
        if ($sellingPrice < $minSell) {
            Log::info('Product price below minimum, adjusting', [
                'cj_pid' => $pid,
                'raw_cost' => $rawCost,
                'current_price' => $sellingPrice,
                'minimum_price' => $minSell
            ]);
            $sellingPrice = $minSell;
        }

        return (float) $sellingPrice;
    }

    /**
     * CRITICAL FIX: Enhanced price validation with stricter corruption prevention
     */
    private function validateAndCalculateVariantPrice(mixed $rawSell, float $rawCost, Product $product, string $pid, string $vid): float
    {
        $pricing = PricingService::makeFromConfig();

        // Initialize with safe defaults
        $sellPrice = is_numeric($rawSell) ? (float) $rawSell : ($product->selling_price ?? 0);

        // Validate basic numeric constraints
        if (!is_numeric($sellPrice) || $sellPrice < 0) {
            $minSell = $pricing->minSellingPrice($rawCost, $product->currency ?? 'USD');
            $sellPrice = $minSell;

            Log::warning('Invalid variant price, using minimum', [
                'cj_pid' => $pid,
                'cj_vid' => $vid,
                'raw_sell' => $rawSell,
                'corrected_price' => $sellPrice
            ]);
        }

        // CRITICAL FIX: Enhanced corruption detection with multiple validation layers
        $maxAllowedMarkup = (float) config('services.cj.max_markup_multiplier', 15.0);
        $corruptionThreshold = $rawCost * $maxAllowedMarkup;

        if ($sellPrice > $corruptionThreshold) {
            Log::error('CRITICAL: Extreme price corruption detected', [
                'cj_pid' => $pid,
                'cj_vid' => $vid,
                'raw_cost' => $rawCost,
                'corrupted_price' => $sellPrice,
                'corruption_threshold' => $corruptionThreshold,
                'markup_multiplier' => $sellPrice / max($rawCost, 0.01)
            ]);

            // Force minimum price for corruption
            $minSell = $pricing->minSellingPrice($rawCost, $product->currency ?? 'USD');
            return $minSell;
        }

        // Additional sanity check for reasonable price ranges
        $reasonableMarkup = (float) config('services.cj.reasonable_markup_multiplier', 10.0);
        $maxReasonablePrice = $rawCost * $reasonableMarkup;

        if ($sellPrice > $maxReasonablePrice) {
            Log::warning('Unreasonable variant price detected, applying maximum reasonable price', [
                'cj_pid' => $pid,
                'cj_vid' => $vid,
                'raw_cost' => $rawCost,
                'variant_sell_price' => $sellPrice,
                'max_reasonable' => $maxReasonablePrice,
                'markup_multiplier' => $sellPrice / max($rawCost, 0.01)
            ]);
            $sellPrice = $maxReasonablePrice;
        }

        // Final validation: ensure price covers costs with minimum margin
        $minSell = $pricing->minSellingPrice($rawCost, $product->currency ?? 'USD');
        if ($sellPrice < $minSell) {
            Log::info('Price below minimum, adjusting', [
                'cj_pid' => $pid,
                'cj_vid' => $vid,
                'raw_cost' => $rawCost,
                'current_price' => $sellPrice,
                'minimum_price' => $minSell
            ]);
            $sellPrice = $minSell;
        }

        return (float) $sellPrice;
    }

    /**
     * CRITICAL FIX: Standardized inventory data extraction with priority validation
     * Made public for use by SyncCjVariantsJob
     */
    public function extractVariantStockWithValidation(array $variant, string $vid, string $pid): array
    {
        $defaultWarehouse = env('CJ_DEFAULT_WAREHOUSE', 'CN');
        $cjStock = 0;
        $stockDataSource = 'none';
        $stockDebugInfo = [
            'cj_pid' => $pid,
            'cj_vid' => $vid,
            'default_warehouse' => $defaultWarehouse,
            'variant_keys' => array_keys($variant)
        ];

        // PRIORITY 1: New inventories structure with CN warehouse validation
        if (isset($variant['inventories']) && is_array($variant['inventories'])) {
            $stockDebugInfo['inventories_count'] = count($variant['inventories']);

            // First try: Find exact warehouse match
            foreach ($variant['inventories'] as $index => $inventory) {
                if (!is_array($inventory)) continue;

                $countryCode = $inventory['countryCode'] ?? null;
                if ($countryCode === $defaultWarehouse) {
                    $cjStock = (int) ($inventory['totalInventory'] ?? $inventory['cjInventory'] ?? $inventory['factoryInventory'] ?? 0);
                    $stockDataSource = "inventories_{$defaultWarehouse}_index_{$index}";
                    $stockDebugInfo['primary_source'] = [
                        'type' => 'warehouse_match',
                        'index' => $index,
                        'country' => $countryCode,
                        'total_inventory' => $inventory['totalInventory'] ?? null,
                        'cj_inventory' => $inventory['cjInventory'] ?? null,
                        'factory_inventory' => $inventory['factoryInventory'] ?? null,
                        'extracted_stock' => $cjStock
                    ];
                    break;
                }
            }

            // Fallback: Use any available warehouse if no CN match found
            if ($cjStock === 0 && !empty($variant['inventories'])) {
                foreach ($variant['inventories'] as $index => $inventory) {
                    if (!is_array($inventory)) continue;

                    $stockValue = $inventory['totalInventory'] ?? $inventory['cjInventory'] ?? $inventory['factoryInventory'] ?? 0;
                    if ((int) $stockValue > 0) {
                        $cjStock = (int) $stockValue;
                        $stockDataSource = "inventories_fallback_index_{$index}";
                        $stockDebugInfo['fallback_source'] = [
                            'type' => 'any_warehouse',
                            'index' => $index,
                            'country' => $inventory['countryCode'] ?? 'unknown',
                            'stock_value' => $stockValue,
                            'extracted_stock' => $cjStock
                        ];
                        break;
                    }
                }
            }
        }

        // PRIORITY 2: Legacy direct fields with strict validation
        if ($cjStock === 0) {
            $legacyFields = [
                'totalInventoryNum' => 10, // highest priority
                'totalInventory' => 9,
                'inventoryNum' => 8,
                'cjInventory' => 7,
                'variantStock' => 6,
                'stock' => 5,
                'variantInventory' => 4,
                'factoryInventory' => 3,
                'availableStock' => 2,
                'quantity' => 1  // lowest priority
            ];

            $stockDebugInfo['legacy_fields_checked'] = [];

            foreach ($legacyFields as $field => $priority) {
                if (isset($variant[$field])) {
                    $value = $variant[$field];
                    $stockDebugInfo['legacy_fields_checked'][] = [
                        'field' => $field,
                        'priority' => $priority,
                        'value' => $value,
                        'is_numeric' => is_numeric($value)
                    ];

                    if (is_numeric($value) && (int) $value > 0) {
                        $cjStock = (int) $value;
                        $stockDataSource = "legacy_field_{$field}_priority_{$priority}";
                        $stockDebugInfo['legacy_source'] = [
                            'field' => $field,
                            'priority' => $priority,
                            'value' => $value,
                            'extracted_stock' => $cjStock
                        ];
                        break;
                    }
                } else {
                    $stockDebugInfo['legacy_fields_checked'][] = [
                        'field' => $field,
                        'priority' => $priority,
                        'value' => 'missing'
                    ];
                }
            }
        }

        // Final validation and logging
        $stockOnHand = $this->calculateStockOnHand($cjStock);

        if ($cjStock === 0) {
            Log::warning('Zero stock extracted - full investigation', $stockDebugInfo);
        } else {
            Log::info('Stock extracted successfully', array_merge($stockDebugInfo, [
                'cj_stock' => $cjStock,
                'stock_on_hand' => $stockOnHand,
                'data_source' => $stockDataSource
            ]));
        }

        return [
            'cj_stock' => $cjStock,
            'stock_on_hand' => $stockOnHand,
            'data_source' => $stockDataSource,
            'debug_info' => $stockDebugInfo
        ];
    }

    /**
     * CRITICAL FIX: Atomic variant creation with enhanced validation
     */
    private function createVariantWithValidation(
        Product $product,
        string $vid,
        ?string $sku,
        string $title,
        float $sellPrice,
        float $rawCost,
        array $options,
        array $variant,
        int $variantStock,
        int $variantStockOnHand,
        string $pid
    ): void {
        // CRITICAL FIX: Validate SKU uniqueness within product scope
        if ($sku) {
            $existingSku = ProductVariant::where('product_id', $product->id)
                ->where('sku', $sku)
                ->where('cj_vid', '!=', $vid) // Exclude current variant if updating
                ->first();

            if ($existingSku) {
                Log::error('SKU conflict detected - CJ API SKU already exists in product', [
                    'cj_pid' => $pid,
                    'cj_vid' => $vid,
                    'conflicting_sku' => $sku,
                    'existing_vid' => $existingSku->cj_vid,
                    'product_id' => $product->id,
                    'action' => 'SKIPPING - CJ API SKU conflict must be resolved at source'
                ]);

                // DO NOT generate local SKU - skip this variant or use null
                $sku = null; // Force null to avoid local generation
            }
        }

        // Parse variant dimensions
        $variantLength = $this->parsePositiveInt($variant['variantLength'] ?? null);
        $variantWidth = $this->parsePositiveInt($variant['variantWidth'] ?? null);
        $variantHeight = $this->parsePositiveInt($variant['variantHeight'] ?? null);
        $variantWeight = $this->parsePositiveInt($variant['variantWeight'] ?? null);

        // Prepare variant data with validation
        $price = $sellPrice;
        if (!is_numeric($price) || $price <= 0) {
            $price = $rawCost > 0 ? $rawCost : 0.01;
        }

        $variantData = [
            'product_id' => $product->id,
            'cj_vid' => $vid ?: null,
            'sku' => $sku, // ONLY use CJ API SKU - no local generation
            'title' => $title,
            'price' => $price,
            'cost_price' => $rawCost, // From CJ API
            'currency' => $product->currency ?? 'USD',
            'variant_image' => $variant['variantImage'] ?? null,
            'options' => $options === [] ? null : $options,
            'weight_grams' => $variantWeight,
            'package_length_mm' => $variantLength,
            'package_width_mm' => $variantWidth,
            'package_height_mm' => $variantHeight,
            'stock_on_hand' => $variantStockOnHand,
            'cj_stock' => $variantStock,
            'cj_stock_synced_at' => now(),
            'metadata' => [
                'cj_vid' => $vid,
                'cj_variant' => $variant,
                'inventory_data' => $variant['inventories'] ?? null,
                'selected_country' => env('CJ_DEFAULT_WAREHOUSE', 'CN'),
                'extracted_stock' => $variantStock,
                'validation_version' => '2.0',
                'sync_timestamp' => now()->toISOString()
            ],
        ];

        // CRITICAL FIX: Use transaction to ensure atomic variant creation
        try {
            DB::transaction(function () use ($variantData, $product, $pid, $vid) {
                $createdVariant = ProductVariant::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'cj_vid' => $variantData['cj_vid'],
                    ],
                    $variantData
                );

                // Validate the created variant
                if (!$createdVariant->exists || $createdVariant->product_id !== $product->id) {
                    throw new \RuntimeException('Variant creation validation failed');
                }

                Log::info('Variant created/updated successfully', [
                    'cj_pid' => $pid,
                    'cj_vid' => $vid,
                    'variant_id' => $createdVariant->id,
                    'sku' => $createdVariant->sku,
                    'price' => $createdVariant->price,
                    'cj_stock' => $createdVariant->cj_stock,
                    'stock_on_hand' => $createdVariant->stock_on_hand
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Atomic variant creation failed', [
                'cj_pid' => $pid,
                'cj_vid' => $vid,
                'sku' => $sku,
                'error' => $e->getMessage(),
                'variant_data_keys' => array_keys($variantData)
            ]);
            throw $e;
        }
    }

    /**
     * Attach inventory payloads to variants by calling the stock-by-vid endpoint
     * when inventories are missing. Follows CJ doc shape.
     *
     * @param array<int,array<string,mixed>> $variants
     * @return array<int,array<string,mixed>>
     */
    private function attachInventoriesToVariants(array $variants): array
    {
        return collect($variants)
            ->map(function ($variant) {
                if (!is_array($variant)) {
                    return $variant;
                }

                $hasInventories = isset($variant['inventories']) && is_array($variant['inventories']) && $variant['inventories'] !== [];
                $vid = (string)($variant['vid'] ?? '');

                if ($hasInventories || $vid === '') {
                    return $variant;
                }

                try {
                    $stockResp = $this->client->getStockByVid($vid);
                    $stockData = $stockResp->data ?? null;

                    if (is_array($stockData) && $stockData !== []) {
                        $inventories = [];
                        foreach ($stockData as $warehouse) {
                            if (!is_array($warehouse)) {
                                continue;
                            }
                            $inventories[] = [
                                'countryCode' => $warehouse['countryCode'] ?? null,
                                'totalInventory' => $warehouse['totalInventoryNum'] ?? $warehouse['storageNum'] ?? 0,
                                'cjInventory' => $warehouse['cjInventoryNum'] ?? $warehouse['inventory'] ?? 0,
                                'factoryInventory' => $warehouse['factoryInventoryNum'] ?? $warehouse['factoryInventory'] ?? 0,
                                'verifiedWarehouse' => $warehouse['verifiedWarehouse'] ?? null,
                                'stock' => $warehouse['stock'] ?? [],
                            ];
                        }

                        if ($inventories !== []) {
                            $variant['inventories'] = $inventories;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to hydrate inventories for variant', ['vid' => $vid, 'error' => $e->getMessage()]);
                }

                return $variant;
            })
            ->values()
            ->all();
    }
}
