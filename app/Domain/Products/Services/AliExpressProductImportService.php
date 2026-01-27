<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\Category;
use App\Domain\Products\Models\ProductVariant;
use App\Domain\Products\Services\PricingService;
use App\Infrastructure\Fulfillment\Clients\AliExpressClient;
use App\Services\ProductMarginLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AliExpressProductImportService
{
    public function __construct(
        private readonly AliExpressClient $client,
    )
    {
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
            'min' => $params['min'] ?? null,
            'max' => $params['max'] ?? null,
            'pageSize' => $params['pageSize'] ?? 20,
            'pageIndex' => $params['pageIndex'] ?? 1,
            'inStockOnly' => isset($params['inStockOnly']) ? ($params['inStockOnly'] ? 'true' : 'false') : null,
        ], fn($v) => $v !== null && $v !== '');

        Log::info('AliExpress search payload', $payload);

        $results = $this->client->searchProducts($payload);

        $items = $results['data']['products'] ?? [];

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
            if (!$aliId) {
                continue;
            }

            $product = $this->importById($aliId, [
                'ship_to_country' => 'CN',
            ]);
            if ($product) {
                $imported[] = $product;
            }
        }

        return $imported;
    }

    public function importById( $aliId, array $options = []): ?Product
    {
        try {
            $productResp = $this->client->getProduct([
                'product_id' => $aliId,
                'ship_to_country' => "CN"
            ]);

            if (!isset($productResp['result']) || !is_array($productResp['result'])) {
                Log::warning('AliExpress product not found', ['product_id' => $aliId]);
                return null;
            }

            $productData = $productResp['result'];
            return $this->mapAndSaveProduct($productData);
        } catch (\Exception $e) {
            Log::error('AliExpress product import failed', [
                'product_id' => $aliId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    public function importBySearch(array $params = []): array
    {
        try {

            $params = [
                "countryCode" => "CN",
                'categoryId'=>$params['categoryId'],
                "local" => "en_US",
                "currency" => "USD",
                ...$params,
            ];
            $payload = array_filter([
                // REQUIRED by docs
                'local'       => $params['local']       ?? 'en_US',
                'countryCode' => $params['countryCode'] ?? 'CN',   // ship-to country (CHANGE as needed)
                'currency'    => $params['currency']    ?? 'USD',

                // Optional per docs
                'categoryId'  => $params['categoryId'] ? (int)$params['categoryId'] : null,
                'keyWord'     => $params['keyWord'] ?? null,
                'sortBy'      => $params['sortBy'] ?? null,
                'pageSize'    => isset($params['pageSize']) ? (int)$params['pageSize'] : 20,
                'pageIndex'   => isset($params['pageIndex']) ? (int)$params['pageIndex'] : 1,

                // Price filters per docs are min/max (NOT minPrice/maxPrice)
                'min'         => $params['min'] ?? $params['minPrice'] ?? null,
                'max'         => $params['max'] ?? $params['maxPrice'] ?? null,
            ], fn($v) => $v !== null && $v !== '');

            Log::info('AliExpress searchProducts payload', $payload);

            $results = $this->client->searchProducts($payload);
            Log::info('AliExpress searchProducts response meta', [
                'has_data' => isset($results['data']),
                'totalCount' => $results['data']['totalCount'] ?? null,
                'pageIndex' => $results['data']['pageIndex'] ?? null,
                'pageSize' => $results['data']['pageSize'] ?? null,
                'products_count' => isset($results['data']['products']) ? count($results['data']['products']) : 0,
            ]);

            $products = $results['data']['products'] ?? [];
            if (empty($products)) {
                Log::warning('AliExpress search returned no products', [
                    'payload' => $payload,
                    'response' => $results,
                ]);
                return [];
            }

            $imported = [];

            foreach ($products as $p) {
                $itemId = $p['itemId'] ?? null;
                if (!$itemId) {
                    continue;
                }

                $product = $this->importById($itemId, [
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

//    public function importBySearch(array $params = []): array
//    {
//        try {
//            $params = [
//                "countryCode" => "CN",
//                'category_id'=>$params['ali_category_id'],
//                "local" => "en_US",
//                "currency" => "USD",
//                ...$params,
//            ];
//            dd($params);
//                $results = $this->client->searchProducts($params);
//            $imported = [];
//
//            dd($results);
//            if (!isset($results['data']) || !isset($results['data']['products'])) {
//                Log::warning('AliExpress search returned no products', ['params' => $params]);
//                return $imported;
//            }
//            foreach ($results['data']['products'] as $productData) {
//                $r = $this->importById($productData['itemId'], []);
//                dd($r);
////                $product = $this->mapAndSaveProduct($productData);
////                if ($product) {
////                    $imported[] = $product;
////                }
//            }
//
//            return $imported;
//        } catch (\Exception $e) {
//            Log::error('AliExpress product search failed', [
//                'params' => $params,
//                'error' => $e->getMessage(),
//            ]);
//            return [];
//        }
//    }
    /**
     * Search AliExpress (ds.text.search) and return the RAW response
     * without importing/saving anything.
     *
     * Accepts the same mixed keys as importBySearch(), normalizes them,
     * builds the correct payload, then calls $this->client->searchProducts().
     */
    public function searchOnly(array $params = []): array
    {
        try {
            // Normalize categoryId coming from different callers
            $categoryId = $params['categoryId']
                ?? $params['category_id']
                ?? $params['ali_category_id']
                ?? null;

            $payload = array_filter([
                // REQUIRED by docs
                'local'       => $params['local']       ?? 'en_US',
                'countryCode' => $params['countryCode'] ?? 'CI',
                'currency'    => $params['currency']    ?? 'USD',

                // Optional per docs
                'categoryId'  => $categoryId ? (int) $categoryId : null,
                'keyWord'     => $params['keyWord'] ?? $params['keyword'] ?? null,
                'sortBy'      => $params['sortBy'] ?? null,
                'pageSize'    => isset($params['pageSize']) ? (int) $params['pageSize']
                    : (isset($params['page_size']) ? (int) $params['page_size'] : 20),
                'pageIndex'   => isset($params['pageIndex']) ? (int) $params['pageIndex'] : 1,

                // Price filters per docs are min/max (NOT minPrice/maxPrice)
                'min'         => $params['min'] ?? $params['minPrice'] ?? $params['min_price'] ?? null,
                'max'         => $params['max'] ?? $params['maxPrice'] ?? $params['max_price'] ?? null,

                // Extra optional fields supported by the docs (pass-through)
                'selectionName' => $params['selectionName'] ?? null,
                'searchKey'     => $params['searchKey'] ?? null,
                'searchValue'   => $params['searchValue'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            Log::info('AliExpress searchOnly payload', $payload);

            $results = $this->client->searchProducts($payload);
    //dd($results);
            Log::info('AliExpress searchOnly response meta', [
                'has_data'        => isset($results['data']),
                'code'            => $results['code'] ?? null,
                'msg'             => $results['msg'] ?? null,
                'totalCount'      => $results['data']['totalCount'] ?? null,
                'pageIndex'       => $results['data']['pageIndex'] ?? null,
                'pageSize'        => $results['data']['pageSize'] ?? null,
                'products_count'  => isset($results['data']['products']) && is_array($results['data']['products'])
                    ? count($results['data']['products'])
                    : 0,
            ]);

            return $results;
        } catch (\Exception $e) {
            Log::error('AliExpress searchOnly failed', [
                'params' => $params,
                'error'  => $e->getMessage(),
            ]);

            // Keep return type stable
            return [
                'code' => 'EXCEPTION',
                'msg'  => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    private function mapAndSaveProduct(array $productData): ?Product
    {
        try {
            $aliItemId = $this->resolveAliItemId($productData);

            if ($aliItemId === null && ! empty($productData['source_url'])) {
                $aliItemId = 'url-' . md5($productData['source_url']);
            }
            $mainProductId = data_get($productData, 'product_id_converter_result.main_product_id');
            $slugIdentifier = $mainProductId !== null ? (string) $mainProductId : $aliItemId;

            $slug = $this->buildAliProductSlug($slugIdentifier);

            $categoryId = $this->resolveCategoryId($productData);

            $providerId = FulfillmentProvider::where('code', 'ae')->value('id');
            $sourceUrl = $this->resolveProductUrl($productData);

            $product = $this->resolveExistingProduct($aliItemId, $sourceUrl, $slug);
            $isNewProduct = $product === null;

            // Pricing: DS -> use offer_sale_price as supplier cost baseline
            $variantPricing = $this->resolveVariantPricing($productData['ae_item_sku_info_dtos'] ?? []);
            $targetSalePrice = $this->normalizePrice($productData['targetSalePrice'] ?? null);
            $targetOriginalPrice = $this->normalizePrice($productData['targetOriginalPrice'] ?? null);

            $cost = $targetSalePrice ?? $variantPricing['min_price'] ?? null;

            $currency = $this->resolveCurrency($productData);

            $pricing = PricingService::makeFromConfig();
            $sellingPrice = $cost !== null ? $pricing->minSellingPrice($cost) : null;

            $attributes = $this->buildAliAttributes($productData, $aliItemId, $currency, $targetSalePrice, $targetOriginalPrice);

            // Preserve Ali category id in attributes (without breaking FK)
            $attributes['ae_item_base_category_id'] = data_get($productData, 'ae_item_base_info_dto.category_id');
            $attributes['ali_category_id'] = (string) (data_get($productData, 'ae_item_base_info_dto.category_id')
                ?? data_get($productData, 'ali_category_id')
                ?? '');

            $payload = [
                'name' => data_get($productData, 'ae_item_base_info_dto.subject') ?? 'AliExpress Product',
                'description' => data_get($productData, 'ae_item_base_info_dto.detail'),
                'selling_price' => $sellingPrice,
                'cost_price' => $cost,
                'currency' => $currency,

                // local category FK (or null)
                'category_id' => $categoryId,

                'is_active' => true,
                'source_url' => $sourceUrl,
                'supplier_product_url' => $productData['source_url'] ?? $sourceUrl,

                'options' => $this->buildOptions($productData['ae_item_sku_info_dtos'] ?? []),
                'stock_on_hand' => $this->resolveStock($productData),
                'default_fulfillment_provider_id' => $providerId,
                'supplier_id' => $providerId,

                'attributes' => array_merge(
                    is_array($product?->attributes) ? $product->attributes : [],
                    $attributes
                ),

                'seo_metadata' => [
                    'title' => data_get($productData, 'ae_item_base_info_dto.subject'),
                    'description' => mb_substr(
                        strip_tags((string) data_get($productData, 'ae_item_base_info_dto.detail', '')),
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
                    if ($this->isSlugUniqueViolation($e)) {
                        $product = Product::where('slug', $slug)->first();
                        if (! $product) {
                            throw $e;
                        }
                        $product->fill($payload);
                        $product->save();
                    } else {
                        throw $e;
                    }
                }
            }

            $product->refresh();

            $logger = app(ProductMarginLogger::class);
            $logger->logProduct($product, [
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

            $this->syncVariants($product, $productData['ae_item_sku_info_dtos'] ?? []);
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
        $aliCategoryId =
            $productData['ali_category_id']
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

        // Optional name fallback (only if your sync guarantees the names)
        $categoryName = trim((string) (
            $productData['category_name']
            ?? $productData['ali_category_name']
            ?? ''
        ));

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
            ['ali_category_id' => $aliCategoryId],
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
            $parent = Category::where('ali_category_id', $parentAliId)->first();
            if ($parent && (int) $category->parent_id !== (int) $parent->id) {
                $category->updateQuietly(['parent_id' => $parent->id]);
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

//        dd($response);
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

        if ($sourceUrl) {
            return Product::where('source_url', $sourceUrl)->first();
        }

        return null;
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
        return $productData['targetOriginalPriceCurrency']
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
            'ali_weight_grams' => $productData['package_weight_grams'] ?? null,
            'supplier_code' => 'aliexpress',

            'ae_store_info' => $productData['ae_store_info'] ?? null,
            'ae_item_sku_info' => $productData['ae_item_sku_info_dtos'] ?? [],
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

            // keep raw response for audit/debug
            'ae_raw' => $productData,
        ];
    }

    private function buildOptions(array $skuInfo): array
    {
        return array_values(array_filter(array_map(function ($sku) {
            if (! is_array($sku)) {
                return null;
            }

            $properties = [];
            foreach ($sku['ae_sku_property_dtos'] ?? [] as $prop) {
                $key = $prop['sku_property_name'] ?? $prop['attr_name'] ?? 'option';
                $value = $prop['property_value_definition_name'] ?? $prop['sku_property_value'] ?? null;
                if ($value !== null) {
                    $properties[$key] = $value;
                }
            }

            $skuPrice = $this->normalizePrice($sku['sku_price'] ?? null);
            $offerPrice = $this->normalizePrice($sku['offer_sale_price'] ?? null);
            $bulkPrice = $this->normalizePrice($sku['offer_bulk_sale_price'] ?? null);

            return [
                'sku_id' => $sku['sku_id'] ?? null,
                'sku_code' => $sku['sku_code'] ?? $sku['id'] ?? null,
                'sku_attr' => $sku['sku_attr'] ?? null,

                // keep both for transparency
                'price' => $skuPrice ?? $offerPrice,
                'offer_sale_price' => $offerPrice,
                'bulk_price' => $bulkPrice,

                'currency' => $sku['currency_code'] ?? null,
                'price_include_tax' => isset($sku['price_include_tax']) ? (bool) $sku['price_include_tax'] : null,
                'stock' => isset($sku['sku_available_stock'])
                    ? (int) $sku['sku_available_stock']
                    : (isset($sku['ipm_sku_stock']) ? (int) $sku['ipm_sku_stock'] : null),

                'properties' => $properties,
                'metadata' => [
                    'raw' => $sku,
                ],
            ];
        }, $skuInfo), fn ($option) => $option !== null));
    }

    private function syncVariants(Product $product, array $skuInfo): void
    {
        if (empty($skuInfo)) {
            $this->ensureDefaultVariant($product);
            return;
        }

        $currency = $product->currency ?? 'USD';
        $pricing = PricingService::makeFromConfig();

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

            // ✅ GLOBAL UNIQUE SKU (matches your DB constraint)
            $skuIdentifier = 'ali:' . (string) $aliSkuId;

            $skuPrice = $this->normalizePrice($sku['sku_price'] ?? null);
            $offerPrice = $this->normalizePrice($sku['offer_sale_price'] ?? null);

            // supplier cost baseline
            $variantCost = $offerPrice ?? $product->cost_price;

            // selling price based on cost
            $variantPrice = ($variantCost !== null && $variantCost > 0)
                ? $pricing->minSellingPrice($variantCost)
                : ($product->selling_price ?? 0);

            $title = $this->buildVariantTitle($sku);

            $properties = [];
            foreach ($sku['ae_sku_property_dtos'] ?? [] as $prop) {
                $key = $prop['sku_property_name'] ?? $prop['attr_name'] ?? 'option';
                $value = $prop['property_value_definition_name'] ?? $prop['sku_property_value'] ?? null;
                if ($value !== null) {
                    $properties[$key] = $value;
                }
            }

            $stock = isset($sku['sku_available_stock'])
                ? (int) $sku['sku_available_stock']
                : (isset($sku['ipm_sku_stock']) ? (int) $sku['ipm_sku_stock'] : null);

            $variantPayload = [
                // product_id will be set by upsert method on create, and optionally on reattach
                'title' => $title,
                'price' => $variantPrice,
                'cost_price' => $variantCost ?? 0,
                'currency' => $currency,
                'options' => [
                    'properties' => $properties,
                    'sku_code' => $sku['sku_code'] ?? null,
                    'sku_attr' => $sku['sku_attr'] ?? null,
                ],
                'metadata' => [
                    'ali_sku_id' => (string) $aliSkuId,
                    'ali_sku_code' => $sku['sku_code'] ?? null,
                    'ali_sku_attr' => $sku['sku_attr'] ?? null,
                    'compare_at' => $skuPrice,
                    'raw' => $sku,
                ],
                'stock_on_hand' => $stock,
            ];

            $this->upsertVariantByGlobalSku($product, $skuIdentifier, $variantPayload);
        }
    }

    private function upsertVariantByGlobalSku(Product $product, string $skuIdentifier, array $variantPayload): void
    {
        // Global unique: sku
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
            // concurrency-safe fallback
            if ($this->isVariantSkuUniqueViolation($e)) {
                $variant = ProductVariant::where('sku', $skuIdentifier)->first();
                if ($variant) {
                    if ((int) $variant->product_id !== (int) $product->id) {
                        $variantPayload['product_id'] = $product->id;
                    }
                    $variant->fill($variantPayload);
                    $variant->save();
                    return;
                }
            }
            throw $e;
        }
    }
    private function ensureDefaultVariant(Product $product): void
    {
        if ($product->variants()->exists()) {
            return;
        }

        // sku is globally unique - don't use slug
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'default:' . $product->id,
            'title' => 'Default',
            'price' => $product->selling_price ?? 0,
            'cost_price' => $product->cost_price ?? 0,
            'currency' => $product->currency ?? 'USD',
            'metadata' => [
                'source' => 'aliexpress',
            ],
        ]);
    }

    private function buildVariantTitle(array $sku): string
    {
        if (isset($sku['ae_sku_property_dtos']) && is_array($sku['ae_sku_property_dtos'])) {
            $parts = [];
            foreach ($sku['ae_sku_property_dtos'] as $prop) {
                $value = $prop['property_value_definition_name'] ?? $prop['sku_property_value'] ?? null;
                if ($value !== null) {
                    $parts[] = $value;
                }
            }
            if ($parts !== []) {
                return implode(' / ', $parts);
            }
        }

        return $sku['sku_code'] ?? $sku['id'] ?? 'Variant';
    }
    private function resolveVariantPricing(array $skuInfo): array
    {
        if ($skuInfo === []) {
            return ['min_price' => null, 'max_price' => null];
        }

        $prices = [];
        foreach ($skuInfo as $sku) {
            if (! is_array($sku)) {
                continue;
            }

            // DS: use offer_sale_price as the cost-ish baseline
            $price = $this->normalizePrice($sku['offer_sale_price'] ?? $sku['sku_price'] ?? null);
            if ($price !== null) {
                $prices[] = $price;
            }
        }

        if ($prices === []) {
            return ['min_price' => null, 'max_price' => null];
        }

        return [
            'min_price' => min($prices),
            'max_price' => max($prices),
        ];
    }
    private function resolveStock(array $productData): ?int
    {
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
