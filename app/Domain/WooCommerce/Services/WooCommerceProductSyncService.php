<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Services;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductImage;
use App\Domain\Products\Models\ProductVariant;
use App\Domain\Products\Services\PricingService;
use App\Domain\WooCommerce\Contracts\WooCommerceClientContract;
use App\Domain\WooCommerce\Contracts\WooCommerceProductSyncContract;
use App\Domain\WooCommerce\DTOs\WooCommerceSyncResult;
use App\Domain\WooCommerce\Models\WooCommerceProductMap;
use App\Domain\WooCommerce\Services\WooCommerceLogService;
use App\Infrastructure\WooCommerce\WooCommerceApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WooCommerceProductSyncService implements WooCommerceProductSyncContract
{
    public function __construct(
        private readonly WooCommerceClientContract $client,
        private readonly PricingService $pricing,
        private readonly WooCommerceLogService $log,
    ) {
    }

    public function syncProduct(Product $product): WooCommerceSyncResult
    {
        try {
            $product->loadMissing(['category', 'images', 'variants', 'defaultFulfillmentProvider']);

            $existing = WooCommerceProductMap::query()
                ->where('product_id', $product->id)
                ->first();

            if ($existing && $existing->sync_hash === $this->computeSyncHash($product) && $existing->status === 'synced') {
                return WooCommerceSyncResult::skipped('Product has not changed', $product->id);
            }

            $data = $this->buildProductPayload($product);

            if ($existing) {
                $response = $this->client->updateProduct($existing->woocommerce_product_id, $data);
                $wooId = $existing->woocommerce_product_id;
            } else {
                $response = $this->client->createProduct($data);
                $wooId = (int) ($response['id'] ?? 0);
            }

            if ($product->type === 'variable') {
                $this->syncVariants($product, $wooId);
            } elseif ($product->type === 'simple') {
                $this->syncSingleVariantAsProduct($product, $wooId);
            }

            $this->updateProductMap($product, $wooId);

            $this->log->success('product', $product->id, 'sync', [
                'woocommerce_product_id' => $wooId,
            ]);

            return WooCommerceSyncResult::success($product->id, $wooId);
        } catch (WooCommerceApiException $e) {
            $this->handleSyncFailure($product, $e);

            return WooCommerceSyncResult::failed($e->getMessage(), $product->id);
        } catch (\Throwable $e) {
            Log::error('WooCommerce product sync failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return WooCommerceSyncResult::failed($e->getMessage(), $product->id);
        }
    }

    public function syncProducts(array $productIds): array
    {
        $results = [];

        foreach (Product::whereIn('id', $productIds)->cursor() as $product) {
            $results[$product->id] = $this->syncProduct($product);
        }

        return $results;
    }

    public function deleteProduct(Product $product): WooCommerceSyncResult
    {
        $map = WooCommerceProductMap::query()
            ->where('product_id', $product->id)
            ->first();

        if (! $map) {
            return WooCommerceSyncResult::skipped('No WooCommerce mapping found', $product->id);
        }

        try {
            $this->client->deleteProduct($map->woocommerce_product_id);

            $map->delete();

            $this->log->info('product', $product->id, 'delete', [
                'woocommerce_product_id' => $map->woocommerce_product_id,
            ]);

            return WooCommerceSyncResult::success($product->id, $map->woocommerce_product_id);
        } catch (WooCommerceApiException $e) {
            if ($e->isNotFound()) {
                $map->delete();

                return WooCommerceSyncResult::success($product->id, $map->woocommerce_product_id);
            }

            return WooCommerceSyncResult::failed($e->getMessage(), $product->id);
        }
    }

    public function importProductFromWooCommerce(int $woocommerceProductId, ?int $variationId = null): WooCommerceSyncResult
    {
        try {
            if ($variationId !== null) {
                $data = $this->client->getVariation($woocommerceProductId, $variationId);
            } else {
                $data = $this->client->getProduct($woocommerceProductId);
            }

            if ($data->sku === '' && $data->type !== 'variable') {
                return WooCommerceSyncResult::skipped('Product has no SKU');
            }

            $product = $this->findOrCreateProduct($data);

            $this->updateProductMap($product, $woocommerceProductId, $variationId);

            return WooCommerceSyncResult::success($product->id, $woocommerceProductId);
        } catch (WooCommerceApiException $e) {
            return WooCommerceSyncResult::failed($e->getMessage());
        }
    }

    public function computeSyncHash(Product $product): string
    {
        $parts = [
            $product->name,
            $product->description ?? '',
            $product->selling_price ?? '',
            $product->cost_price ?? '',
            $product->stock_on_hand ?? 0,
            $product->weight ?? 0,
            $product->is_active ? 'active' : 'inactive',
            $product->category?->name ?? '',
            $product->images->pluck('url')->implode(','),
            $product->variants->map(fn (ProductVariant $v) => implode('|', [
                $v->sku, $v->title, $v->price, $v->stock_on_hand, $v->weight_grams,
            ]))->implode(';'),
        ];

        return hash('sha256', implode('||', $parts));
    }

    public function needsSync(Product $product): bool
    {
        $map = WooCommerceProductMap::query()
            ->where('product_id', $product->id)
            ->first();

        if (! $map) {
            return true;
        }

        if ($map->status === 'failed') {
            return true;
        }

        return $map->sync_hash !== $this->computeSyncHash($product);
    }

    private function buildProductPayload(Product $product): array
    {
        $data = [
            'name' => $product->name,
            'slug' => $product->slug,
            'type' => $product->type === 'variable' ? 'variable' : 'simple',
            'status' => $product->is_active ? 'publish' : 'draft',
            'description' => $product->description ?? '',
            'short_description' => $product->meta_description ?? '',
            'regular_price' => $product->selling_price ? (string) $product->selling_price : '',
            'manage_stock' => true,
            'stock_quantity' => max(0, (int) ($product->stock_on_hand ?? 0)),
            'stock_status' => ($product->stock_on_hand ?? 0) > 0 ? 'instock' : 'outofstock',
            'weight' => $product->weight ? (string) $product->weight : '',
        ];

        if ($product->category) {
            $data['categories'] = [
                ['id' => $this->syncCategory($product->category)],
            ];
        }

        $images = $product->images->sortBy('position')->values();
        if ($images->isNotEmpty()) {
            $first = $images->shift();
            $data['images'] = [
                [
                    'src' => $first->url,
                    'position' => 0,
                ],
            ];
            foreach ($images as $index => $image) {
                $data['images'][] = [
                    'src' => $image->url,
                    'position' => $index + 1,
                ];
            }
        }

        $data['meta_data'] = [
            ['key' => '_laravel_product_id', 'value' => (string) $product->id],
            ['key' => '_laravel_sync_source', 'value' => 'laravel'],
        ];

        return $data;
    }

    private function syncVariants(Product $product, int $wooProductId): void
    {
        foreach ($product->variants as $variant) {
            $existing = WooCommerceProductMap::query()
                ->where('product_variant_id', $variant->id)
                ->first();

            $payload = [
                'regular_price' => $variant->price ? (string) $variant->price : '',
                'manage_stock' => true,
                'stock_quantity' => max(0, (int) ($variant->stock_on_hand ?? 0)),
                'sku' => $variant->sku,
            ];

            if ($variant->weight_grams) {
                $payload['weight'] = (string) ($variant->weight_grams / 1000);
            }

            if ($variant->variant_image) {
                $payload['image'] = ['src' => $variant->variant_image];
            }

            if ($variant->options) {
                $attributes = [];
                foreach ($variant->options as $name => $value) {
                    $attributes[] = [
                        'name' => $name,
                        'option' => $value,
                    ];
                }
                $payload['attributes'] = $attributes;
            }

            try {
                if ($existing) {
                    $this->client->updateVariation($wooProductId, $existing->woocommerce_variation_id, $payload);
                } else {
                    $response = $this->client->createVariation($wooProductId, $payload);
                    $wooVariationId = (int) ($response['id'] ?? 0);

                    WooCommerceProductMap::updateOrCreate(
                        ['product_variant_id' => $variant->id],
                        [
                            'product_id' => $product->id,
                            'woocommerce_product_id' => $wooProductId,
                            'woocommerce_variation_id' => $wooVariationId,
                            'sku' => $variant->sku,
                            'status' => 'synced',
                            'last_synced_at' => now(),
                        ],
                    );
                }
            } catch (WooCommerceApiException $e) {
                Log::warning('Failed to sync WooCommerce variation', [
                    'variant_id' => $variant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function syncSingleVariantAsProduct(Product $product, int $wooProductId): void
    {
        $variant = $product->variants->first();

        if (! $variant) {
            return;
        }

        WooCommerceProductMap::updateOrCreate(
            ['product_variant_id' => $variant->id],
            [
                'product_id' => $product->id,
                'woocommerce_product_id' => $wooProductId,
                'sku' => $variant->sku,
                'status' => 'synced',
                'last_synced_at' => now(),
            ],
        );
    }

    private function syncCategory(\App\Domain\Products\Models\Category $category): int
    {
        if ($category->parent_id) {
            $parentId = $this->syncCategory($category->parent);

            $this->ensureCategoryInWooCommerce($category, $parentId);

            return $category->woocommerce_id ?? $this->createCategoryInWooCommerce($category, $parentId);
        }

        $this->ensureCategoryInWooCommerce($category);

        return $category->woocommerce_id ?? $this->createCategoryInWooCommerce($category);
    }

    private function ensureCategoryInWooCommerce(\App\Domain\Products\Models\Category $category, ?int $parentId = null): void
    {
        if (! property_exists($category, 'woocommerce_id') || ! $category->woocommerce_id) {
            return;
        }

        try {
            $this->client->updateCategory($category->woocommerce_id, [
                'name' => $category->name,
                'parent' => $parentId ?? 0,
            ]);
        } catch (WooCommerceApiException $e) {
            if ($e->isNotFound()) {
                $category->woocommerce_id = null;
            }
        }
    }

    private function createCategoryInWooCommerce(\App\Domain\Products\Models\Category $category, ?int $parentId = null): int
    {
        $response = $this->client->createCategory([
            'name' => $category->name,
            'slug' => $category->slug,
            'parent' => $parentId ?? 0,
        ]);

        $wooId = (int) ($response['id'] ?? 0);

        if ($wooId > 0 && method_exists($category, 'update')) {
            $category->woocommerce_id = $wooId;
            $category->save();
        }

        return $wooId;
    }

    private function findOrCreateProduct(\App\Domain\WooCommerce\DTOs\WooCommerceProductData $data): Product
    {
        $map = WooCommerceProductMap::query()
            ->where('woocommerce_product_id', $data->woocommerceId)
            ->first();

        if ($map && $map->product) {
            return $map->product;
        }

        if ($data->sku !== '') {
            $variant = ProductVariant::query()->where('sku', $data->sku)->first();

            if ($variant && $variant->product) {
                $this->importImages($variant->product, $data);
                return $variant->product;
            }
        }

        $product = Product::query()->create([
            'name' => $data->name,
            'description' => $data->description,
            'selling_price' => $data->regularPrice ?? 0,
            'is_active' => $data->status === 'publish',
            'stock_on_hand' => $data->stockQuantity ?? 0,
            'weight' => $data->weight,
            'slug' => $data->slug,
        ]);

        $this->importImages($product, $data);

        return $product;
    }

    private function importImages(Product $product, \App\Domain\WooCommerce\DTOs\WooCommerceProductData $data): void
    {
        if (empty($data->images)) {
            return;
        }

        $product->images()->delete();

        foreach ($data->images as $index => $image) {
            $src = $image['src'] ?? $image['url'] ?? null;

            if ($src) {
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'url' => $src,
                    'position' => $image['position'] ?? $index,
                ]);
            }
        }

        if ($data->type === 'variation' && $data->woocommerceVariationId) {
            $variant = ProductVariant::query()
                ->whereHas('product', fn ($q) => $q->where('id', $product->id))
                ->where('sku', $data->sku)
                ->first();

            if ($variant && ! empty($data->images)) {
                $variant->variant_image = $data->images[0]['src'] ?? $data->images[0]['url'] ?? null;
                $variant->save();
            }
        }
    }

    private function updateProductMap(Product $product, int $wooProductId, ?int $variationId = null): void
    {
        WooCommerceProductMap::updateOrCreate(
            ['product_id' => $product->id],
            [
                'woocommerce_product_id' => $wooProductId,
                'woocommerce_variation_id' => $variationId,
                'sku' => $product->variants->first()?->sku ?? $product->code,
                'sync_hash' => $this->computeSyncHash($product),
                'status' => 'synced',
                'last_error' => null,
                'last_synced_at' => now(),
            ],
        );
    }

    private function handleSyncFailure(Product $product, WooCommerceApiException $e): void
    {
        WooCommerceProductMap::query()
            ->where('product_id', $product->id)
            ->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);

        $this->log->error('product', $product->id, 'sync', $e->getMessage(), [
            'status_code' => $e->getStatusCode(),
        ]);
    }
}
