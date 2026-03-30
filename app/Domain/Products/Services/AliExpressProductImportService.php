<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Products\Models\Category;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Domain\Products\Models\SupplierProduct;
use App\Infrastructure\Fulfillment\Clients\AliExpressClient;
use App\Models\LocalWareHouse;
use App\Services\ProductMarginLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AliExpressProductImportService
{
    private const SEARCH_PAGE_LIMIT = 40;

    public function __construct(
        private readonly AliExpressClient $client,
    ) {
    }

    public function search(array $params = []): array
    {
        $params = [
            ...$params,
            'local' => $params['local'] ?? 'en_US',
            'countryCode' => $params['countryCode'] ?? 'CN',
            'currency' => $params['currency'] ?? 'USD',
        ];

        $payload = array_filter([
            'local' => $params['local'],
            'countryCode' => $params['countryCode'],
            'currency' => $params['currency'],
            'categoryId' => $params['categoryId'] ?? null,
            'keyWord' => $params['keyWord'] ?? null,
            'minRating' => $params['minRating'] ?? $params['min_rating'] ?? null,
            'min' => $params['min'] ?? null,
            'max' => $params['max'] ?? null,
            'pageSize' => $params['pageSize'] ?? 40,
            'pageIndex' => $params['pageIndex'] ?? 1,
            'inStockOnly' => isset($params['inStockOnly']) ? ($params['inStockOnly'] ? 'true' : 'false') : null,
        ], fn ($v) => $v !== null && $v !== '');

        Log::info('AliExpress search payload', $payload);

        $results = $this->client->searchProducts($payload);
        $items = $this->extractSearchProducts($results);

        return array_map(function ($item) {
            $productId = $item['productId'] ?? $item['itemId'] ?? null;

            return [
                'productId' => $productId,
                'itemId' => $item['itemId'] ?? null,
                'productTitle' => $item['subject'] ?? $item['product_title'] ?? 'Untitled',
                'salePrice' => $item['salePrice'] ?? $item['price'] ?? null,
                'feedbackScore' => $item['feedbackScore'] ?? $item['ratings'] ?? null,
                'ali_category_id' => $item['ali_category_id'] ?? null,
                'raw' => $item,
            ];
        }, $items);
    }

    public function importSelected(array $items): array
    {
        $imported = [];

        foreach ($items as $item) {
            $aliId = $item['productId'] ?? $item['itemId'] ?? null;
            if (! $aliId) {
                continue;
            }

            $product = $this->importById((string) $aliId, [
                'ship_to_country' => 'CN',
            ]);

            if ($product) {
                $imported[] = $product;
            }
        }

        return $imported;
    }

    public function buildImportPreviewById(string $aliId, array $options = []): ?array
    {
        $productData = $this->fetchProductPayload($aliId, $options);

        return is_array($productData) ? $this->buildImportPreview($productData, $options) : null;
    }

    public function importById($aliId, array $options = []): ?Product
    {
        try {
            $productData = $this->fetchProductPayload((string) $aliId, $options);

            if (! is_array($productData)) {
                Log::warning('AliExpress product not found', ['product_id' => $aliId]);

                return null;
            }

            return $this->mapAndSaveProduct($productData, $options);
        } catch (\Exception $e) {
            Log::error('AliExpress product import failed', [
                'product_id' => $aliId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getLiveSkuStatus(string $aliItemId, string $aliSkuId, array $options = []): ?array
    {
        $aliItemId = trim($aliItemId);
        $aliSkuId = trim($aliSkuId);

        if ($aliItemId === '' || $aliSkuId === '') {
            return null;
        }

        $productData = $this->fetchProductPayload($aliItemId, $options);
        if (! is_array($productData)) {
            return null;
        }

        foreach ($this->extractAliSkuRows($productData) as $sku) {
            if ((string) ($sku['sku_id'] ?? '') !== $aliSkuId) {
                continue;
            }

            return [
                'ali_item_id' => $aliItemId,
                'ali_sku_id' => $aliSkuId,
                'stock' => $this->resolveSkuStock($sku),
                'sku' => $sku,
                'product' => $productData,
            ];
        }

        return null;
    }

    public function refreshVariantLiveStock(ProductVariant $variant, array $options = []): ?int
    {
        $variant->loadMissing('product');

        $metadata = is_array($variant->metadata ?? null) ? $variant->metadata : [];
        $productAttributes = is_array($variant->product?->attributes ?? null) ? $variant->product->attributes : [];
        $aliItemId = trim((string) ($productAttributes['ali_item_id'] ?? $metadata['ali_item_id'] ?? ''));
        $aliSkuId = trim((string) ($metadata['ali_sku_id'] ?? ''));

        if ($aliItemId === '' || $aliSkuId === '') {
            return null;
        }

        $status = $this->getLiveSkuStatus($aliItemId, $aliSkuId, $options);
        if (! is_array($status)) {
            return null;
        }

        $liveStock = $status['stock'];
        if ($liveStock !== null && $variant->stock_on_hand !== $liveStock) {
            $variant->forceFill(['stock_on_hand' => $liveStock])->save();
        }

        return $liveStock;
    }

    public function importBySearch(array $params = []): array
    {
        try {
            $params = [
                'countryCode' => 'CN',
                'categoryId' => $params['categoryId'] ?? null,
                'local' => 'en_US',
                'currency' => 'USD',
                'pageSize' => $params['pageSize'] ?? null,
                ...$params,
            ];

            $payload = array_filter([
                'local' => $params['local'] ?? 'en_US',
                'countryCode' => $params['countryCode'] ?? 'CN',
                'currency' => $params['currency'] ?? 'USD',
                'categoryId' => $params['categoryId'] ? (int) $params['categoryId'] : null,
                'keyWord' => $params['keyWord'] ?? null,
                'sortBy' => $params['sortBy'] ?? null,
                'pageSize' => isset($params['pageSize']) ? (int) $params['pageSize'] : 20,
                'pageIndex' => isset($params['pageIndex']) ? (int) $params['pageIndex'] : 1,
                'min' => $params['min'] ?? $params['minPrice'] ?? null,
                'max' => $params['max'] ?? $params['maxPrice'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            Log::info('AliExpress searchProducts payload', $payload);

            $results = $this->client->searchProducts($payload);
            Log::info('AliExpress searchProducts response meta', [
                'has_data' => isset($results['data']),
                'totalCount' => $results['data']['totalCount'] ?? data_get($results, 'result.totalCount'),
                'pageIndex' => $results['data']['pageIndex'] ?? data_get($results, 'result.pageIndex'),
                'pageSize' => $results['data']['pageSize'] ?? data_get($results, 'result.pageSize'),
                'products_count' => count($this->extractSearchProducts($results)),
            ]);

            $products = $this->extractSearchProducts($results);
            if ($products === []) {
                Log::warning('AliExpress search returned no products', [
                    'payload' => $payload,
                    'response' => $results,
                ]);

                return [];
            }

            $imported = [];

            foreach ($products as $p) {
                $itemId = $p['itemId'] ?? null;
                if (! $itemId) {
                    continue;
                }

                $product = $this->importById((string) $itemId, [
                    'ship_to_country' => $payload['countryCode'],
                ]);

                if ($product) {
                    $imported[] = $product;
                }
            }

            return $imported;
        } catch (\Exception $e) {
            Log::error('AliExpress product search failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function searchWithAutoPaging(array $params = [], int $targetCount = 40, int $startPage = 1): array
    {
        $target = max(1, $targetCount);
        $perPage = $params['pageSize'] ?? $target;
        $perPage = max(1, min((int) $perPage, self::SEARCH_PAGE_LIMIT));
        $collected = [];
        $pageIndex = max(1, $startPage);
        $firstResponse = null;

        while (count($collected) < $target) {
            $payload = array_filter([
                ...$params,
                'pageSize' => min($perPage, $target - count($collected)),
                'pageIndex' => $pageIndex,
            ], fn ($value) => $value !== null && $value !== '');

            $response = $this->searchOnly($payload);
            if (! is_array($response)) {
                break;
            }

            $firstResponse ??= $response;
            $items = $this->extractSearchProducts($response);

            if ($items === []) {
                break;
            }

            $collected = [...$collected, ...$items];
            $pageIndex++;
        }

        $limited = array_slice($collected, 0, $target);

        return [
            'data' => [
                'products' => $limited,
            ],
            'meta' => [
                'requested' => $target,
                'pages_fetched' => $pageIndex,
                'totalCount' => $firstResponse['data']['totalCount'] ?? null,
            ],
        ];
    }

    public function searchPage(array $params = [], int $pageIndex = 1, ?int $pageSize = null): array
    {
        $pageIndex = max(1, $pageIndex);
        $pageSize = $pageSize ?? ($params['pageSize'] ?? self::SEARCH_PAGE_LIMIT);
        $pageSize = max(1, min((int) $pageSize, self::SEARCH_PAGE_LIMIT));

        $payload = array_filter([
            ...$params,
            'pageIndex' => $pageIndex,
            'pageSize' => $pageSize,
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->searchOnly($payload);
        $products = $this->extractSearchProducts($response);
        $rawTotalCount = $response['data']['totalCount'] ?? data_get($response, 'result.totalCount');
        $totalCount = is_numeric($rawTotalCount) ? (int) $rawTotalCount : null;
        if ($totalCount !== null && $totalCount <= 0) {
            $totalCount = null;
        }

        $responsePageSize = $response['data']['pageSize'] ?? data_get($response, 'result.pageSize');
        $effectivePageSize = is_numeric($responsePageSize) ? (int) $responsePageSize : $pageSize;

        $exhausted = empty($products);
        if ($totalCount !== null) {
            $exhausted = ($pageIndex * $effectivePageSize) >= $totalCount;
        } elseif ($effectivePageSize > 0 && count($products) < $effectivePageSize) {
            $exhausted = true;
        }

        return [
            'items' => $products,
            'pageIndex' => $pageIndex,
            'pageSize' => $effectivePageSize,
            'totalCount' => $totalCount,
            'exhausted' => $exhausted,
            'hasMore' => ! $exhausted,
            'nextPage' => $exhausted ? null : ($pageIndex + 1),
            'raw' => $response,
        ];
    }

    public function searchOnly(array $params = []): array
    {
        try {
            $params = [
                'countryCode' => 'CN',
                'categoryId' => $params['categoryId'] ?? null,
                'local' => 'en_US',
                'currency' => 'USD',
                'page_size' => $params['pageSize'] ?? null,
                ...$params,
            ];

            $payload = array_filter([
                'local' => $params['local'] ?? 'en_US',
                'countryCode' => $params['countryCode'] ?? 'CN',
                'currency' => $params['currency'] ?? 'USD',
                'categoryId' => $params['categoryId'] ? (int) $params['categoryId'] : null,
                'keyWord' => $params['keyWord'] ?? $params['keyword'] ?? null,
                'minRating' => $params['minRating'] ?? $params['min_rating'] ?? null,
                'sortBy' => $params['sortBy'] ?? null,
                'pageSize' => isset($params['pageSize']) ? (int) $params['pageSize']
                    : (isset($params['page_size']) ? (int) $params['page_size'] : 20),
                'pageIndex' => isset($params['pageIndex']) ? (int) $params['pageIndex'] : 1,
                'min' => $params['min'] ?? $params['minPrice'] ?? $params['min_price'] ?? null,
                'max' => $params['max'] ?? $params['maxPrice'] ?? $params['max_price'] ?? null,
                'selectionName' => $params['selectionName'] ?? null,
                'searchKey' => $params['searchKey'] ?? null,
                'searchValue' => $params['searchValue'] ?? null,
                'inStockOnly' => isset($params['inStockOnly']) ? ($params['inStockOnly'] ? 'true' : 'false') : null,
            ], fn ($v) => $v !== null && $v !== '');

            Log::info('AliExpress searchOnly payload', $payload);

            $results = $this->client->searchProducts($payload);
            $products = $this->extractSearchProducts($results);

            if ($products === [] && ! empty($payload['keyWord'])) {
                $fallback = [
                    ...$payload,
                    'searchKey' => $payload['searchKey'] ?? 'keywords',
                    'searchValue' => $payload['searchValue'] ?? $payload['keyWord'],
                ];
                unset($fallback['keyWord']);

                Log::info('AliExpress searchOnly fallback payload', $fallback);

                $fallbackResults = $this->client->searchProducts($fallback);
                $fallbackProducts = $this->extractSearchProducts($fallbackResults);

                if ($fallbackProducts !== []) {
                    $results = $fallbackResults;
                    $products = $fallbackProducts;
                }
            }

            Log::info('AliExpress searchOnly response meta', [
                'has_data' => isset($results['data']),
                'code' => $results['code'] ?? null,
                'msg' => $results['msg'] ?? null,
                'totalCount' => $results['data']['totalCount'] ?? data_get($results, 'result.totalCount'),
                'pageIndex' => $results['data']['pageIndex'] ?? data_get($results, 'result.pageIndex'),
                'pageSize' => $results['data']['pageSize'] ?? data_get($results, 'result.pageSize'),
                'products_count' => count($products),
            ]);

            return $results;
        } catch (\Exception $e) {
            Log::error('AliExpress searchOnly failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return [
                'code' => 'EXCEPTION',
                'msg' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    private function fetchProductPayload(string $aliId, array $options = []): ?array
    {
        $requestOptions = array_filter([
            'ship_to_country' => $options['ship_to_country'] ?? null,
            'countryCode' => $options['countryCode'] ?? null,
            'local' => $options['local'] ?? null,
            'currency' => $options['currency'] ?? null,
            'target_currency' => $options['target_currency'] ?? null,
            'target_language' => $options['target_language'] ?? null,
            'remove_personal_benefit' => array_key_exists('remove_personal_benefit', $options)
                ? ($options['remove_personal_benefit'] ? 'true' : 'false')
                : null,
            'biz_model' => $options['biz_model'] ?? null,
            'province_code' => $options['province_code'] ?? null,
            'city_code' => $options['city_code'] ?? null,
        ], fn ($value) => is_scalar($value) && $value !== '');

        $productResp = $this->client->getProduct([
            'product_id' => $aliId,
            'ship_to_country' => $options['ship_to_country'] ?? 'CN',
            ...$requestOptions,
        ]);

        return isset($productResp['result']) && is_array($productResp['result'])
            ? $productResp['result']
            : null;
    }

    private function extractSearchProducts(array $response): array
    {
        $candidates = [
            data_get($response, 'data.products'),
            data_get($response, 'data.productList'),
            data_get($response, 'data.productsList'),
            data_get($response, 'data.searchResult'),
            data_get($response, 'data.items'),
            data_get($response, 'result.products'),
            data_get($response, 'result.productList'),
            data_get($response, 'resp_result.result.products'),
            data_get($response, 'resp_result.result.productList'),
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && $candidate !== []) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return [];
    }

    private function buildImportPreview(array $productData, array $options = []): array
    {
        $baseInfo = is_array($productData['ae_item_base_info_dto'] ?? null) ? $productData['ae_item_base_info_dto'] : [];
        $skuRows = $this->extractAliSkuRows($productData);
        $currency = $this->resolveCurrency($productData);
        $variantRows = array_map(function (array $sku) use ($currency, $productData): array {
            $salePrice = $this->normalizePrice($sku['offer_sale_price'] ?? null);
            $compareAt = $this->normalizePrice($sku['sku_price'] ?? null);
            $stock = $this->resolveSkuStock($sku);
            $weightKg = $this->resolveWeightKgForSku($sku, $productData);
            $pricingPreview = $salePrice !== null
                ? $this->buildPricingPreview($salePrice, $weightKg, $currency)
                : null;

            return [
                'sku_id' => (string) ($sku['sku_id'] ?? ''),
                'title' => $this->buildVariantTitle($sku),
                'properties' => $this->extractSkuProperties($sku),
                'sku_attr' => $sku['sku_attr'] ?? null,
                'offer_sale_price' => $salePrice,
                'sku_price' => $compareAt,
                'currency' => $sku['currency_code'] ?? $currency,
                'stock' => $stock,
                'weight_kg' => $weightKg,
                'weight_grams' => $weightKg > 0 ? (int) round($weightKg * 1000) : null,
                'image' => $this->extractSkuImage($sku),
                'pricing_preview' => $pricingPreview,
                'is_valid' => $salePrice !== null && $salePrice > 0 && $stock !== null,
            ];
        }, $skuRows);

        $productWeightKg = $this->resolveWeightKg($productData);
        $productPricingPreview = null;
        $previewCost = null;
        foreach ($variantRows as $variantRow) {
            if (is_numeric($variantRow['offer_sale_price'] ?? null)) {
                $previewCost = (float) $variantRow['offer_sale_price'];
                break;
            }
        }
        if ($previewCost !== null) {
            $productPricingPreview = $this->buildPricingPreview($previewCost, $productWeightKg, $currency);
        }

        $images = $this->extractImageUrls($productData['ae_multimedia_info_dto'] ?? []);
        foreach ($variantRows as $variant) {
            $variantImage = $variant['image'] ?? null;
            if (is_string($variantImage) && $variantImage !== '' && ! in_array($variantImage, $images, true)) {
                $images[] = $variantImage;
            }
        }

        $selectedVariantIds = array_values(array_filter(array_map(
            fn (array $variant): string => (string) ($variant['sku_id'] ?? ''),
            array_filter($variantRows, fn (array $variant): bool => (bool) ($variant['is_valid'] ?? false))
        )));

        return [
            'ali_item_id' => $this->resolveAliItemId($productData),
            'title' => (string) ($baseInfo['subject'] ?? 'AliExpress Product'),
            'description' => $this->resolveProductDescription($productData),
            'description_html' => (string) ($baseInfo['detail'] ?? ''),
            'mobile_description_html' => (string) ($baseInfo['mobile_detail'] ?? ''),
            'category_id' => $this->resolveCategoryId($productData),
            'images' => $images,
            'variants' => $variantRows,
            'selected_variant_ids' => $selectedVariantIds,
            'attributes' => $this->normalizeItemProperties($productData['ae_item_properties'] ?? []),
            'store' => is_array($productData['ae_store_info'] ?? null) ? $productData['ae_store_info'] : [],
            'package' => [
                'weight_grams' => $productWeightKg > 0 ? (int) round($productWeightKg * 1000) : null,
                'weight_kg' => $productWeightKg > 0 ? round($productWeightKg, 4) : null,
                'gross_weight' => data_get($productData, 'package_info_dto.gross_weight'),
                'length_mm' => $this->normalizeWholeNumber(data_get($productData, 'package_info_dto.package_length')),
                'width_mm' => $this->normalizeWholeNumber(data_get($productData, 'package_info_dto.package_width')),
                'height_mm' => $this->normalizeWholeNumber(data_get($productData, 'package_info_dto.package_height')),
            ],
            'logistics' => [
                'delivery_time' => $this->normalizeWholeNumber(data_get($productData, 'logistics_info_dto.delivery_time')),
                'ship_to_country' => data_get($productData, 'logistics_info_dto.ship_to_country') ?? ($options['ship_to_country'] ?? 'CN'),
            ],
            'request_options' => [
                'ship_to_country' => $options['ship_to_country'] ?? 'CN',
                'target_currency' => $options['target_currency'] ?? null,
                'target_language' => $options['target_language'] ?? null,
                'remove_personal_benefit' => (bool) ($options['remove_personal_benefit'] ?? false),
                'biz_model' => $options['biz_model'] ?? null,
                'province_code' => $options['province_code'] ?? null,
                'city_code' => $options['city_code'] ?? null,
            ],
            'pricing_preview' => $productPricingPreview,
            'validation' => $this->validateImportPreviewData($variantRows, $images),
            'raw' => $productData,
        ];
    }

    private function validateImportPreviewData(array $variants, array $images): array
    {
        $errors = [];
        $warnings = [];

        if ($variants === []) {
            $errors[] = 'AliExpress returned no variants for this product.';
        }

        $invalidVariants = array_values(array_filter($variants, fn (array $variant): bool => ! ($variant['is_valid'] ?? false)));
        if ($invalidVariants !== []) {
            $errors[] = count($invalidVariants) . ' variant(s) are missing a valid price or stock value.';
        }

        if ($images === []) {
            $warnings[] = 'No product images were returned by AliExpress.';
        }

        return [
            'is_valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'variants_count' => count($variants),
            'valid_variants_count' => count($variants) - count($invalidVariants),
            'images_count' => count($images),
        ];
    }

    private function mapAndSaveProduct(array $productData, array $options = []): ?Product
    {
        try {
            $aliItemId = $this->resolveAliItemId($productData);
            if ($aliItemId === null && ! empty($productData['source_url'])) {
                $aliItemId = 'url-' . md5((string) $productData['source_url']);
            }

            $mainProductId = data_get($productData, 'product_id_converter_result.main_product_id');
            $slugIdentifier = $mainProductId !== null ? (string) $mainProductId : $aliItemId;
            $slug = $this->buildAliProductSlug($slugIdentifier);

            $categoryId = isset($options['category_id']) && $options['category_id'] !== ''
                ? (int) $options['category_id']
                : $this->resolveCategoryId($productData);
            $providerId = $this->resolveAliProviderId();
            $sourceUrl = $this->resolveProductUrl($productData);
            $product = $this->resolveExistingProduct($aliItemId, $sourceUrl, $slug);
            $localWarehouseId = $this->resolveAliLocalWarehouseId($product);
            $isNewProduct = $product === null;

            $skuRows = $this->filterImportSkuRows(
                $this->extractAliSkuRows($productData),
                $options['enabled_variant_ids'] ?? null
            );
            $variantPricing = $this->resolveVariantPricing($skuRows);
            $targetSalePrice = $this->normalizePrice($productData['targetSalePrice'] ?? null);
            $targetOriginalPrice = $this->normalizePrice($productData['targetOriginalPrice'] ?? null);
            $cost = $targetSalePrice ?? $variantPricing['min_price'] ?? null;
            $currency = $this->resolveCurrency($productData);
            $productWeightKg = $this->resolveWeightKg($productData);
            $pricingPreview = $cost !== null ? $this->buildPricingPreview($cost, $productWeightKg, $currency) : null;
            $sellingPrice = $pricingPreview['selling_price'] ?? ($cost !== null ? PricingService::makeFromConfig()->minSellingPrice($cost) : null);

            $attributes = $this->buildAliAttributes($productData, $aliItemId, $currency, $targetSalePrice, $targetOriginalPrice);
            $attributes['ae_item_base_category_id'] = data_get($productData, 'ae_item_base_info_dto.category_id');
            $attributes['ali_category_id'] = (string) (data_get($productData, 'ae_item_base_info_dto.category_id')
                ?? data_get($productData, 'ali_category_id')
                ?? '');

            $payload = [
                'name' => (string) ($options['title'] ?? data_get($productData, 'ae_item_base_info_dto.subject') ?? 'AliExpress Product'),
                'description' => (string) ($options['description'] ?? $this->resolveProductDescription($productData)),
                'selling_price' => $sellingPrice,
                'cost_price' => $cost,
                'currency' => $currency,
                'supplier_currency' => $currency,
                'category_id' => $categoryId,
                'is_active' => true,
                'supplier_type' => 'aliexpress',
                'source_url' => $sourceUrl,
                'supplier_product_url' => $productData['source_url'] ?? $sourceUrl,
                'options' => $this->buildOptions($skuRows),
                'stock_on_hand' => $this->resolveStock($productData),
                'local_warehouse_id' => $localWarehouseId,
                'default_fulfillment_provider_id' => $providerId,
                'supplier_id' => $providerId,
                'pricing_meta' => $pricingPreview['pricing_meta'] ?? null,
                'attributes' => array_merge(
                    is_array($product?->attributes) ? $product->attributes : [],
                    $attributes
                ),
                'seo_metadata' => [
                    'title' => (string) ($options['title'] ?? data_get($productData, 'ae_item_base_info_dto.subject')),
                    'description' => mb_substr(
                        strip_tags((string) ($options['description'] ?? $this->resolveProductDescription($productData))),
                        0,
                        160
                    ),
                ],
            ];

            if ($product) {
                $product->fill($payload);
                if (! $product->slug) {
                    $product->slug = $slug;
                }
                $product->save();
            } else {
                $payload['slug'] = $slug;

                try {
                    $product = Product::create($payload);
                } catch (QueryException $e) {
                    if (! $this->isSlugUniqueViolation($e)) {
                        throw $e;
                    }

                    $product = Product::where('slug', $slug)->first();
                    if (! $product) {
                        throw $e;
                    }

                    $product->fill($payload);
                    $product->save();
                }
            }

            $product->refresh();

            app(ProductMarginLogger::class)->logProduct($product, [
                'event' => $isNewProduct ? 'ali_imported' : 'ali_updated',
                'source' => 'aliexpress',
                'old_selling_price' => $isNewProduct ? null : $product->getOriginal('selling_price'),
                'new_selling_price' => $product->selling_price,
                'notes' => "AliExpress import {$aliItemId}",
            ]);

            Log::info('AliExpress product imported', [
                'product_id' => $product->id,
                'ali_item_id' => $aliItemId,
                'cost_price' => $product->cost_price,
                'category_id' => $product->category_id,
            ]);

            $this->syncVariants($product, $skuRows, $productData);
            $this->syncSupplierProducts($product, $skuRows, $providerId, $aliItemId, $currency, $productData);
            $this->syncImages($product, $productData);

            return $product;
        } catch (\Exception $e) {
            Log::error('Failed to map and save AliExpress product', [
                'ali_item_id' => $this->resolveAliItemId($productData),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function buildAliProductSlug(?string $identifier): string
    {
        $normalized = $this->normalizeSlugIdentifier($identifier);
        $base = $normalized ? "aliexpress-product-{$normalized}" : 'aliexpress-product-' . Str::random(6);
        $slug = Str::slug($base);

        return $slug === '' ? 'aliexpress-product-' . Str::random(6) : $slug;
    }

    private function resolveCategoryId(array $productData): ?int
    {
        $aliCategoryId = $productData['ali_category_id']
            ?? data_get($productData, 'ae_item_base_info_dto.category_id')
            ?? null;

        if ($aliCategoryId !== null && $aliCategoryId !== '') {
            $category = Category::query()->where('ali_category_id', (string) $aliCategoryId)->first();
            if ($category) {
                return (int) $category->id;
            }

            $category = $this->ensureAliExpressCategory((string) $aliCategoryId);
            if ($category) {
                return (int) $category->id;
            }

            Log::warning('AliExpress category not mapped (missing sync)', [
                'ali_category_id' => (string) $aliCategoryId,
                'ali_item_id' => $this->resolveAliItemId($productData),
            ]);
        }

        $categoryName = trim((string) ($productData['category_name'] ?? $productData['ali_category_name'] ?? ''));
        if ($categoryName === '') {
            return null;
        }

        $categoryId = Category::where('name', $categoryName)->value('id');
        if (! $categoryId) {
            Log::warning('AliExpress category name fallback not found', [
                'category_name' => $categoryName,
                'ali_item_id' => $this->resolveAliItemId($productData),
            ]);

            return null;
        }

        return (int) $categoryId;
    }

    private function ensureAliExpressCategory(string $aliCategoryId): ?Category
    {
        $payload = $this->fetchAliCategoryPayload($aliCategoryId);
        if (! $payload) {
            return null;
        }

        $name = $payload['category_name'] ?? $payload['name'] ?? "AliExpress {$aliCategoryId}";
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'ali-category-' . Str::random(6);
        }

        $category = Category::firstOrCreate(
            ['ali_category_id' => $payload['category_id']],
            [
                'name' => $name,
                'slug' => $slug,
                'description' => $payload['category_description'] ?? null,
                'is_active' => true,
                'ali_payload' => $payload,
            ]
        );

        $parentAliId = (string) ($payload['parent_category_id'] ?? '');
        if ($parentAliId !== '') {
            $parent = Category::query()->where('ali_category_id', $parentAliId)->first();
            if ($parent) {
                $conflict = Category::query()
                    ->where('name', $category->name)
                    ->where('parent_id', $parent->id)
                    ->where('id', '!=', $category->id)
                    ->first();

                if ($conflict) {
                    Log::warning('AliExpress category parent conflict; reusing existing category', [
                        'ali_category_id' => $aliCategoryId,
                        'category_id' => $category->id,
                        'conflict_id' => $conflict->id,
                        'parent_id' => $parent->id,
                        'name' => $category->name,
                    ]);

                    if (empty($conflict->ali_category_id)) {
                        $conflict->ali_category_id = $aliCategoryId;
                        $conflict->ali_payload = $conflict->ali_payload ?: $payload;
                        $conflict->save();
                    }

                    Product::where('category_id', $category->id)->update(['category_id' => $conflict->id]);

                    return $conflict;
                }

                if ((int) $category->parent_id !== (int) $parent->id) {
                    $category->updateQuietly(['parent_id' => $parent->id]);
                }
            }
        }

        return $category;
    }

    private function fetchAliCategoryPayload(string $aliCategoryId): ?array
    {
        try {
            $response = $this->client->getCategoryById($aliCategoryId);
        } catch (\Exception $e) {
            Log::warning('AliExpress category lookup failed', [
                'ali_category_id' => $aliCategoryId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $categories = collect(data_get($response, 'resp_result.result.categories', []));
        if ($categories->isEmpty()) {
            return null;
        }

        $match = $categories->first(fn ($cat) => (string) ($cat['category_id'] ?? '') === $aliCategoryId);

        return $match ?: $categories->first();
    }

    private function normalizePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function resolveExistingProduct(?string $aliItemId, ?string $sourceUrl, string $slug): ?Product
    {
        if ($aliItemId) {
            $product = Product::where('attributes->ali_item_id', $aliItemId)->first();
            if ($product) {
                return $product;
            }
        }

        $product = Product::where('slug', $slug)->first();
        if ($product) {
            return $product;
        }

        return $sourceUrl ? Product::where('source_url', $sourceUrl)->first() : null;
    }

    private function resolveAliItemId(array $productData): ?string
    {
        foreach (['item_id', 'product_id', 'productId', 'ae_product_id'] as $key) {
            if (! empty($productData[$key])) {
                return (string) $productData[$key];
            }
        }

        if (isset($productData['product_id_converter_result']['main_product_id'])) {
            return (string) $productData['product_id_converter_result']['main_product_id'];
        }

        return null;
    }

    private function resolveCurrency(array $productData): string
    {
        foreach ($this->extractAliSkuRows($productData) as $sku) {
            $code = $sku['currency_code'] ?? $sku['currency'] ?? null;
            if (is_string($code) && $code !== '') {
                return $code;
            }
        }

        return $productData['targetOriginalPriceCurrency']
            ?? $productData['targetSalePriceCurrency']
            ?? data_get($productData, 'ae_item_base_info_dto.currency_code')
            ?? $productData['currency_code']
            ?? 'USD';
    }

    private function buildAliAttributes(
        array $productData,
        ?string $aliItemId,
        string $currency,
        ?float $targetSalePrice,
        ?float $targetOriginalPrice
    ): array {
        return [
            'ali_item_id' => $aliItemId,
            'ali_category_id' => isset($productData['ali_category_id']) ? (string) $productData['ali_category_id'] : null,
            'ali_shop_id' => isset($productData['shop_id']) ? (string) $productData['shop_id'] : null,
            'ali_min_order_quantity' => $productData['min_order_quantity'] ?? null,
            'ali_stock' => $productData['inventoryQuantity'] ?? $productData['inventory_quantity'] ?? null,
            'ali_weight_grams' => $this->resolveWeightKg($productData) > 0 ? (int) round($this->resolveWeightKg($productData) * 1000) : null,
            'ali_gross_weight' => data_get($productData, 'package_info_dto.gross_weight'),
            'supplier_code' => 'aliexpress',
            'supplier_type' => 'aliexpress',
            'product_subject' => data_get($productData, 'ae_item_base_info_dto.subject'),
            'product_detail' => data_get($productData, 'ae_item_base_info_dto.detail'),
            'product_mobile_detail' => data_get($productData, 'ae_item_base_info_dto.mobile_detail'),
            'structured_properties' => $this->normalizeItemProperties($productData['ae_item_properties'] ?? []),
            'ae_store_info' => $productData['ae_store_info'] ?? null,
            'ae_item_sku_info' => $this->extractAliSkuRows($productData),
            'ae_multimedia' => $productData['ae_multimedia_info_dto'] ?? null,
            'ae_multimedia_info_dto' => $productData['ae_multimedia_info_dto'] ?? null,
            'ae_package' => $productData['package_info_dto'] ?? null,
            'ae_logistics' => $productData['logistics_info_dto'] ?? null,
            'ae_item_base_info' => $productData['ae_item_base_info_dto'] ?? null,
            'ae_item_properties' => $productData['ae_item_properties'] ?? null,
            'manufacturer_info' => $productData['manufacturer_info'] ?? null,
            'product_id_converter_result' => $productData['product_id_converter_result'] ?? null,
            'has_whole_sale' => $productData['has_whole_sale'] ?? null,
            'target_prices' => [
                'currency' => $currency,
                'supplier_cost' => $targetSalePrice,
                'compare_at' => $targetOriginalPrice,
                'target_sale_price' => $targetSalePrice,
                'target_original_price' => $targetOriginalPrice,
            ],
            'ae_raw' => $productData,
        ];
    }

    private function buildOptions(array $skuInfo): array
    {
        return array_values(array_filter(array_map(function ($sku) {
            if (! is_array($sku)) {
                return null;
            }

            $skuPrice = $this->normalizePrice($sku['sku_price'] ?? null);
            $offerPrice = $this->normalizePrice($sku['offer_sale_price'] ?? null);
            $bulkPrice = $this->normalizePrice($sku['offer_bulk_sale_price'] ?? null);
            $properties = $this->extractSkuProperties($sku);

            return [
                'sku_id' => $sku['sku_id'] ?? null,
                'sku_code' => $sku['sku_code'] ?? $sku['id'] ?? null,
                'sku_attr' => $sku['sku_attr'] ?? null,
                'price' => $skuPrice ?? $offerPrice,
                'offer_sale_price' => $offerPrice,
                'bulk_price' => $bulkPrice,
                'currency' => $sku['currency_code'] ?? null,
                'price_include_tax' => isset($sku['price_include_tax']) ? (bool) $sku['price_include_tax'] : null,
                'stock' => $this->resolveSkuStock($sku),
                'image' => $this->extractSkuImage($sku),
                'properties' => $properties,
                'metadata' => [
                    'raw' => $sku,
                ],
                ...$properties,
            ];
        }, $skuInfo), fn ($option) => $option !== null));
    }

    private function syncVariants(Product $product, array $skuInfo, array $productData = []): void
    {
        if ($skuInfo === []) {
            $this->ensureDefaultVariant($product);

            return;
        }

        $currency = $product->currency ?? 'USD';
        $supplierCurrency = $product->supplier_currency ?? $currency;
        $pricing = PricingService::makeFromConfig();
        $package = is_array($productData['package_info_dto'] ?? null) ? $productData['package_info_dto'] : [];
        $defaultWeightKg = $this->resolveWeightKg($productData);

        foreach ($skuInfo as $sku) {
            if (! is_array($sku)) {
                continue;
            }

            $aliSkuId = $sku['sku_id'] ?? null;
            if (empty($aliSkuId)) {
                Log::warning('AliExpress SKU missing sku_id (skipping)', [
                    'product_id' => $product->id,
                    'sku_code' => $sku['sku_code'] ?? null,
                    'sku_attr' => $sku['sku_attr'] ?? null,
                ]);

                continue;
            }

            $skuIdentifier = 'ali:' . (string) $aliSkuId;
            $skuPrice = $this->normalizePrice($sku['sku_price'] ?? null);
            $offerPrice = $this->normalizePrice($sku['offer_sale_price'] ?? null);
            $variantCost = $offerPrice ?? $product->cost_price;
            $variantWeightKg = $this->resolveWeightKgForSku($sku, $productData);
            if ($variantWeightKg <= 0) {
                $variantWeightKg = $defaultWeightKg;
            }
            $variantPricingPreview = ($variantCost !== null && $variantCost > 0)
                ? $this->buildPricingPreview($variantCost, $variantWeightKg, $currency)
                : null;
            $variantPrice = $variantPricingPreview['selling_price'] ?? (($variantCost !== null && $variantCost > 0)
                ? $pricing->minSellingPrice($variantCost)
                : ($product->selling_price ?? 0));

            $variantPayload = [
                'title' => $this->buildVariantTitle($sku),
                'price' => $variantPrice,
                'compare_at_price' => $skuPrice,
                'cost_price' => $variantCost ?? 0,
                'currency' => $currency,
                'supplier_currency' => $supplierCurrency,
                'variant_image' => $this->extractSkuImage($sku),
                'weight_grams' => $variantWeightKg > 0
                    ? (int) round($variantWeightKg * 1000)
                    : $this->normalizeWholeNumber($sku['sku_weight'] ?? ($package['package_weight'] ?? null)),
                'package_length_mm' => $this->normalizeWholeNumber($sku['package_length'] ?? ($package['package_length'] ?? null)),
                'package_width_mm' => $this->normalizeWholeNumber($sku['package_width'] ?? ($package['package_width'] ?? null)),
                'package_height_mm' => $this->normalizeWholeNumber($sku['package_height'] ?? ($package['package_height'] ?? null)),
                'options' => $this->buildVariantOptions($sku),
                'metadata' => [
                    'ali_sku_id' => (string) $aliSkuId,
                    'ali_sku_code' => $sku['sku_code'] ?? null,
                    'ali_sku_attr' => $sku['sku_attr'] ?? null,
                    'compare_at' => $skuPrice,
                    'supplier_type' => 'aliexpress',
                    'pricing_meta' => $variantPricingPreview['pricing_meta'] ?? null,
                    'raw' => $sku,
                ],
                'stock_on_hand' => $this->resolveSkuStock($sku),
            ];

            $this->upsertVariantByGlobalSku($product, $skuIdentifier, $variantPayload);
        }
    }

    private function upsertVariantByGlobalSku(Product $product, string $skuIdentifier, array $variantPayload): void
    {
        $variant = ProductVariant::where('sku', $skuIdentifier)->first();

        if ($variant) {
            if ((int) $variant->product_id !== (int) $product->id) {
                Log::warning('AliExpress variant SKU attached to another product, reattaching', [
                    'sku' => $skuIdentifier,
                    'from_product_id' => $variant->product_id,
                    'to_product_id' => $product->id,
                ]);
                $variantPayload['product_id'] = $product->id;
            }

            $variant->fill($variantPayload);
            $variant->save();

            return;
        }

        try {
            ProductVariant::create(array_merge(
                ['product_id' => $product->id, 'sku' => $skuIdentifier],
                $variantPayload
            ));
        } catch (QueryException $e) {
            if (! $this->isVariantSkuUniqueViolation($e)) {
                throw $e;
            }

            $variant = ProductVariant::where('sku', $skuIdentifier)->first();
            if ($variant) {
                if ((int) $variant->product_id !== (int) $product->id) {
                    $variantPayload['product_id'] = $product->id;
                }
                $variant->fill($variantPayload);
                $variant->save();

                return;
            }

            throw $e;
        }
    }

    private function ensureDefaultVariant(Product $product): void
    {
        if ($product->variants()->exists()) {
            return;
        }

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'default:' . $product->id,
            'title' => 'Default',
            'price' => $product->selling_price ?? 0,
            'cost_price' => $product->cost_price ?? 0,
            'currency' => $product->currency ?? 'USD',
            'supplier_currency' => $product->supplier_currency ?? 'USD',
            'metadata' => [
                'source' => 'aliexpress',
                'supplier_type' => 'aliexpress',
            ],
        ]);
    }

    private function buildVariantTitle(array $sku): string
    {
        $parts = array_values(array_filter($this->extractSkuProperties($sku)));
        if ($parts !== []) {
            return implode(' / ', $parts);
        }

        return $sku['sku_code'] ?? $sku['id'] ?? 'Variant';
    }

    private function resolveVariantPricing(array $skuInfo): array
    {
        $prices = [];

        foreach ($skuInfo as $sku) {
            if (! is_array($sku)) {
                continue;
            }

            $price = $this->normalizePrice($sku['offer_sale_price'] ?? $sku['sku_price'] ?? null);
            if ($price !== null) {
                $prices[] = $price;
            }
        }

        return [
            'min_price' => $prices === [] ? null : min($prices),
            'max_price' => $prices === [] ? null : max($prices),
        ];
    }

    private function resolveStock(array $productData): ?int
    {
        $skuRows = $this->extractAliSkuRows($productData);
        if ($skuRows !== []) {
            $stock = array_reduce($skuRows, function (int $carry, array $sku): int {
                return $carry + (int) ($this->resolveSkuStock($sku) ?? 0);
            }, 0);

            return $stock > 0 ? $stock : null;
        }

        if (isset($productData['inventoryQuantity'])) {
            return (int) $productData['inventoryQuantity'];
        }

        if (isset($productData['inventory_quantity'])) {
            return (int) $productData['inventory_quantity'];
        }

        return null;
    }

    private function normalizeSlugIdentifier(?string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($value));
        $slug = trim($slug, '-');

        return $slug === '' ? null : $slug;
    }

    private function resolveProductUrl(array $productData): ?string
    {
        $baseInfo = $productData['ae_item_base_info_dto'] ?? [];
        $candidates = [
            $productData['source_url'] ?? null,
            $productData['product_url'] ?? null,
            $productData['productUrl'] ?? null,
            $productData['detail_url'] ?? null,
            $productData['detailUrl'] ?? null,
            $productData['productDetailUrl'] ?? null,
            $baseInfo['detailUrl'] ?? null,
            $baseInfo['productDetailUrl'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function syncImages(Product $product, array $productData): void
    {
        $imageUrls = $this->extractImageUrls($productData['ae_multimedia_info_dto'] ?? []);
        foreach ($this->extractAliSkuRows($productData) as $sku) {
            $variantImage = $this->extractSkuImage($sku);
            if ($variantImage && ! in_array($variantImage, $imageUrls, true)) {
                $imageUrls[] = $variantImage;
            }
        }

        if ($imageUrls === []) {
            return;
        }

        $product->images()->whereNotIn('url', $imageUrls)->delete();

        foreach ($imageUrls as $index => $url) {
            if ($url === '') {
                continue;
            }

            $product->images()->updateOrCreate(
                ['product_id' => $product->id, 'url' => $url],
                ['position' => $index + 1]
            );
        }
    }

    private function extractImageUrls(array $multimedia): array
    {
        $raw = $multimedia['image_urls'] ?? null;
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $urls = array_map(fn ($url) => trim($url), explode(';', $raw));

        return array_values(array_filter($urls, fn ($url) => $url !== ''));
    }

    private function extractAliSkuRows(array $productData): array
    {
        $candidates = [
            $productData['ae_item_sku_info_dtos'] ?? null,
            $productData['ae_item_sku_info'] ?? null,
            $productData['ae_item_sku_info_dto'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return array_values(array_filter($candidate, fn ($row): bool => is_array($row)));
            }
        }

        return [];
    }

    private function resolveAliProviderId(): ?int
    {
        return FulfillmentProvider::query()
            ->whereIn('code', ['ae', 'aliexpress'])
            ->value('id');
    }

    private function resolveAliLocalWarehouseId(?Product $existingProduct = null): ?int
    {
        if ($existingProduct?->local_warehouse_id) {
            return (int) $existingProduct->local_warehouse_id;
        }

        return LocalWareHouse::query()
            ->where('is_default', true)
            ->orderBy('id')
            ->value('id');
    }

    private function extractSkuProperties(array $sku): array
    {
        $properties = [];

        foreach (($sku['ae_sku_property_dtos'] ?? []) as $prop) {
            if (! is_array($prop)) {
                continue;
            }

            $key = $prop['sku_property_name'] ?? $prop['attr_name'] ?? 'option';
            $value = $prop['property_value_definition_name'] ?? $prop['sku_property_value'] ?? null;
            if ($value !== null && $value !== '') {
                $properties[(string) $key] = (string) $value;
            }
        }

        return $properties;
    }

    private function extractSkuImage(array $sku): ?string
    {
        $candidates = [
            $sku['sku_image'] ?? null,
            $sku['sku_image_url'] ?? null,
            $sku['image_url'] ?? null,
            data_get($sku, 'sku_image_info.sku_image'),
            data_get($sku, 'sku_image_info.image_url'),
        ];

        foreach (($sku['ae_sku_property_dtos'] ?? []) as $property) {
            if (! is_array($property)) {
                continue;
            }

            $candidates[] = $property['sku_image'] ?? null;
            $candidates[] = $property['image_url'] ?? null;
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private function buildVariantOptions(array $sku): array
    {
        $properties = $this->extractSkuProperties($sku);

        return array_filter([
            ...$properties,
            'sku_code' => $sku['sku_code'] ?? null,
            'sku_attr' => $sku['sku_attr'] ?? null,
            'properties' => $properties,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function normalizeItemProperties(mixed $properties): array
    {
        if (! is_array($properties)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($property): ?array {
            if (! is_array($property)) {
                return null;
            }

            $name = $property['attr_name'] ?? $property['property_name'] ?? $property['name'] ?? null;
            $value = $property['attr_value'] ?? $property['property_value'] ?? $property['value'] ?? null;
            if (! is_string($name) || trim($name) === '' || $value === null || $value === '') {
                return null;
            }

            return [
                'name' => trim($name),
                'value' => is_scalar($value) ? trim((string) $value) : json_encode($value, JSON_UNESCAPED_UNICODE),
            ];
        }, $properties)));
    }

    private function resolveProductDescription(array $productData): string
    {
        $detail = trim((string) data_get($productData, 'ae_item_base_info_dto.detail', ''));
        $mobileDetail = trim((string) data_get($productData, 'ae_item_base_info_dto.mobile_detail', ''));

        return $detail !== '' ? $detail : $mobileDetail;
    }

    private function normalizeStockValue(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function resolveSkuStock(array $sku): ?int
    {
        $candidates = [
            $sku['sku_available_stock'] ?? null,
            $sku['ipm_sku_stock'] ?? null,
            $sku['sku_stock'] ?? null,
            $sku['available_stock'] ?? null,
            $sku['availableStock'] ?? null,
            $sku['stock'] ?? null,
            $sku['inventory_quantity'] ?? null,
            $sku['inventoryQuantity'] ?? null,
            data_get($sku, 'sku_inventory_info.available_stock'),
            data_get($sku, 'sku_inventory_info.stock'),
            data_get($sku, 'sku_inventory_info.inventory_quantity'),
        ];

        foreach ($candidates as $candidate) {
            $stock = $this->normalizeStockValue($candidate);
            if ($stock !== null) {
                return max(0, $stock);
            }
        }

        return null;
    }

    private function normalizeWholeNumber(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return max(0, (int) round((float) $value));
    }

    private function resolveWeightKg(array $productData): float
    {
        $grossWeight = data_get($productData, 'package_info_dto.gross_weight');
        if (is_numeric($grossWeight)) {
            return max(0, (float) $grossWeight);
        }

        $packageWeight = data_get($productData, 'package_info_dto.package_weight') ?? ($productData['package_weight_grams'] ?? null);
        if (is_numeric($packageWeight)) {
            $value = (float) $packageWeight;
            if ($value <= 0) {
                return 0.0;
            }

            return $value > 10 ? ($value / 1000) : $value;
        }

        return 0.0;
    }

    private function resolveWeightKgForSku(array $sku, array $productData = []): float
    {
        foreach ([
            $sku['gross_weight'] ?? null,
            $sku['sku_weight'] ?? null,
            $sku['weight'] ?? null,
        ] as $candidate) {
            if (! is_numeric($candidate)) {
                continue;
            }

            $value = (float) $candidate;
            if ($value <= 0) {
                continue;
            }

            return $value > 10 ? ($value / 1000) : $value;
        }

        return $productData !== [] ? $this->resolveWeightKg($productData) : 0.0;
    }

    private function buildPricingPreview(float $cost, float $weightKg, string $currency): ?array
    {
        if ($cost <= 0) {
            return null;
        }

        $pricing = PricingService::makeFromConfig();
        $preview = $pricing->previewPricing([
            'product_cost' => $cost,
            'weight_kg' => max(0, $weightKg),
            'cj_shipping' => 0,
            'currency' => $currency,
        ]);

        return [
            'selling_price' => $preview['selling_price'] ?? null,
            'weight_kg' => $preview['weight_kg'] ?? max(0, $weightKg),
            'external_shipping' => $preview['external_shipping'] ?? null,
            'landed_cost' => $preview['landed_cost'] ?? null,
            'margin_percent' => $preview['margin_percent'] ?? null,
            'pricing_meta' => $preview['pricing_meta'] ?? null,
        ];
    }

    private function filterImportSkuRows(array $skuRows, mixed $enabledVariantIds): array
    {
        if (! is_array($enabledVariantIds) || $enabledVariantIds === []) {
            return $skuRows;
        }

        $allowed = array_values(array_unique(array_filter(array_map(
            fn ($value): string => trim((string) $value),
            $enabledVariantIds
        ))));

        if ($allowed === []) {
            return [];
        }

        return array_values(array_filter(
            $skuRows,
            fn (array $sku): bool => in_array((string) ($sku['sku_id'] ?? ''), $allowed, true)
        ));
    }

    private function syncSupplierProducts(
        Product $product,
        array $skuRows,
        ?int $providerId,
        ?string $aliItemId,
        string $currency,
        array $productData
    ): void {
        if (! $providerId) {
            return;
        }

        $leadTimeDays = $this->normalizeWholeNumber(data_get($productData, 'logistics_info_dto.delivery_time', 0)) ?? 0;
        $variantsBySku = $product->variants()->get()->keyBy(
            fn (ProductVariant $variant): string => (string) data_get($variant->metadata, 'ali_sku_id', '')
        );

        foreach ($skuRows as $sku) {
            $aliSkuId = (string) ($sku['sku_id'] ?? '');
            if ($aliSkuId === '' || ! $variantsBySku->has($aliSkuId)) {
                continue;
            }

            $variant = $variantsBySku->get($aliSkuId);

            SupplierProduct::query()->updateOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'fulfillment_provider_id' => $providerId,
                    'external_product_id' => $aliItemId ?? (string) $product->id,
                ],
                [
                    'external_sku' => $aliSkuId,
                    'cost_price' => $this->normalizePrice($sku['offer_sale_price'] ?? null) ?? $variant->cost_price,
                    'currency' => $currency,
                    'lead_time_days' => $leadTimeDays,
                    'shipping_options' => [
                        'ship_to_country' => data_get($productData, 'logistics_info_dto.ship_to_country'),
                        'delivery_time' => data_get($productData, 'logistics_info_dto.delivery_time'),
                    ],
                    'is_active' => true,
                ]
            );
        }
    }

    private function isSlugUniqueViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $errorCode = $e->errorInfo[1] ?? null;

        return $sqlState === '23000' && $errorCode === 1062;
    }

    private function isVariantSkuUniqueViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $errorCode = $e->errorInfo[1] ?? null;
        $message = $e->errorInfo[2] ?? '';

        return $sqlState === '23000'
            && $errorCode === 1062
            && str_contains($message, 'product_variants_sku_unique');
    }
}
