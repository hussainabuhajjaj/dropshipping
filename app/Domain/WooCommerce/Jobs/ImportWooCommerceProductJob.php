<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Jobs;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductImage;
use App\Domain\Products\Models\ProductVariant;
use App\Domain\WooCommerce\Contracts\WooCommerceClientContract;
use App\Domain\WooCommerce\DTOs\WooCommerceProductData;
use App\Domain\WooCommerce\Models\WooCommerceProductMap;
use App\Domain\WooCommerce\Services\WooCommerceLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportWooCommerceProductJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $wooProductId,
        public ?int $categoryId = null,
    ) {
        $this->onQueue('woocommerce');
    }

    public function handle(
        WooCommerceClientContract $client,
        WooCommerceLogService $log,
    ): void {
        try {
            $data = $client->getProduct($this->wooProductId);

            if ($data->sku === '' && $data->type !== 'variable') {
                $log->warning('product', null, 'import-skipped', [
                    'woocommerce_product_id' => $this->wooProductId,
                    'reason' => 'Product has no SKU',
                ]);
                return;
            }

            DB::transaction(function () use ($data, $log): void {
                $product = $this->findOrCreateProduct($data);
                $this->importPricing($product, $data);

                if ($data->type === 'variable') {
                    $this->importVariations($product, $data);
                } else {
                    $this->importSingleVariant($product, $data);
                }

                $this->importImages($product, $data);

                if ($this->categoryId) {
                    $product->category_id = $this->categoryId;
                    $product->save();
                }

                $this->updateMapping($product, $data);

                $log->success('product', $product->id, 'imported', [
                    'woocommerce_product_id' => $this->wooProductId,
                    'name' => $data->name,
                    'type' => $data->type,
                ]);
            });
        } catch (\Throwable $e) {
            Log::channel('woocommerce')->error('Failed to import WooCommerce product', [
                'woocommerce_product_id' => $this->wooProductId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $log->error('product', null, 'import', $e->getMessage(), [
                'woocommerce_product_id' => $this->wooProductId,
            ]);

            throw $e;
        }
    }

    private function findOrCreateProduct(WooCommerceProductData $data): Product
    {
        $map = WooCommerceProductMap::query()
            ->where('woocommerce_product_id', $data->woocommerceId)
            ->first();

        if ($map && $map->product) {
            return tap($map->product, fn (Product $p) => $p->update([
                'name' => $data->name,
                'description' => $data->description ?? $p->description,
                'is_active' => $data->status === 'publish',
                'stock_on_hand' => $data->stockQuantity ?? 0,
                'weight' => $data->weight,
                'slug' => $data->slug ?: Str::slug($data->name),
            ]));
        }

        if ($data->sku !== '') {
            $variant = ProductVariant::query()->where('sku', $data->sku)->first();
            if ($variant && $variant->product) {
                return $variant->product;
            }
        }

        return Product::query()->create([
            'name' => $data->name,
            'code' => $data->sku ?: Product::generateProductCode(),
            'slug' => $data->slug ?: Str::slug($data->name),
            'description' => $data->description,
            'selling_price' => $data->regularPrice ?? 0,
            'is_active' => $data->status === 'publish',
            'stock_on_hand' => $data->stockQuantity ?? 0,
            'weight' => $data->weight,
            'searchable_text' => Str::lower($data->name . ' ' . ($data->description ?? '')),
        ]);
    }

    private function importPricing(Product $product, WooCommerceProductData $data): void
    {
        $updates = [];

        if ($data->regularPrice !== null) {
            $updates['selling_price'] = $data->regularPrice;
        }

        if ($data->salePrice !== null) {
            $updates['cost_price'] = $data->salePrice;
        }

        if ($updates !== []) {
            $product->update($updates);
        }
    }

    private function importVariations(Product $product, WooCommerceProductData $data): void
    {
        $variationIds = $data->variations;

        if ($variationIds === []) {
            return;
        }

        foreach ($variationIds as $variationId) {
            try {
                $variation = app(WooCommerceClientContract::class)->getVariation(
                    $data->woocommerceId,
                    $variationId,
                );

                $this->createOrUpdateVariant($product, $variation);
            } catch (\Throwable $e) {
                Log::channel('woocommerce')->warning('Failed to import variation', [
                    'product_id' => $product->id,
                    'woo_variation_id' => $variationId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function importSingleVariant(Product $product, WooCommerceProductData $data): void
    {
        $this->createOrUpdateVariant($product, $data);
    }

    private function createOrUpdateVariant(Product $product, WooCommerceProductData $data): ProductVariant
    {
        $sku = $data->sku;

        $existing = $sku !== ''
            ? ProductVariant::query()->where('product_id', $product->id)->where('sku', $sku)->first()
            : null;

        $options = [];
        foreach ($data->attributes as $attribute) {
            $name = $attribute['name'] ?? '';
            $option = $attribute['option'] ?? ($attribute['options'][0] ?? null);
            if ($name && $option) {
                $options[$name] = $option;
            }
        }

        $attrs = [
            'title' => $data->name,
            'price' => $data->regularPrice,
            'compare_at_price' => $data->salePrice,
            'stock_on_hand' => $data->stockQuantity ?? 0,
            'weight_grams' => $data->weight !== null ? (int) ($data->weight * 1000) : null,
            'options' => $options,
        ];

        if ($data->images !== []) {
            $attrs['variant_image'] = $data->images[0]['src'] ?? $data->images[0]['url'] ?? null;
        }

        if ($sku !== '') {
            $attrs['sku'] = $sku;
        }

        if ($existing) {
            $existing->update($attrs);
            return $existing;
        }

        $attrs['product_id'] = $product->id;
        if ($sku === '') {
            $attrs['sku'] = $product->code . '-var-' . Str::random(4);
        }

        return ProductVariant::query()->create($attrs);
    }

    private function importImages(Product $product, WooCommerceProductData $data): void
    {
        if ($data->images === []) {
            return;
        }

        $existingCount = $product->images()->count();
        if ($existingCount > 0) {
            return;
        }

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
    }

    private function updateMapping(Product $product, WooCommerceProductData $data): void
    {
        $sku = $data->sku ?: $product->variants->first()?->sku ?? $product->code;

        WooCommerceProductMap::updateOrCreate(
            ['woocommerce_product_id' => $data->woocommerceId],
            [
                'product_id' => $product->id,
                'sku' => $sku,
                'status' => 'synced',
                'last_error' => null,
                'last_synced_at' => now(),
            ],
        );
    }
}
