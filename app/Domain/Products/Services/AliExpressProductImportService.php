<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\Category;
use App\Infrastructure\Fulfillment\Clients\AliExpressClient;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AliExpressProductImportService
{
    public function __construct(
        private readonly AliExpressClient $client,
    ) {}

    public function importById(string $aliId, array $options = []): ?Product
    {
        try {
            $productResp = $this->client->getProduct(['product_id' => $aliId]);
            
            if (!isset($productResp['data']) || !is_array($productResp['data'])) {
                Log::warning('AliExpress product not found', ['product_id' => $aliId]);
                return null;
            }
            
            $productData = $productResp['data'];
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
            $results = $this->client->searchProducts($params);
            $imported = [];
            
            if (!isset($results['data']) || !isset($results['data']['products'])) {
                Log::warning('AliExpress search returned no products', ['params' => $params]);
                return $imported;
            }
            
            foreach ($results['data']['products'] as $productData) {
                $product = $this->mapAndSaveProduct($productData);
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

    private function mapAndSaveProduct(array $productData): ?Product
    {
        try {
            $slug = Str::slug($productData['subject'] ?? 'product');
            
            // Find or create category if provided
            $categoryId = null;
            if (isset($productData['category_id'])) {
                $category = Category::where('name', $productData['category_name'] ?? 'Uncategorized')->firstOrCreate(
                    ['name' => $productData['category_name'] ?? 'Uncategorized'],
                    ['slug' => Str::slug($productData['category_name'] ?? 'uncategorized')]
                );
                $categoryId = $category->id;
            }
            
            $product = Product::updateOrCreate(
                ['source_url' => $productData['item_url'] ?? null],
                [
                    'name' => $productData['subject'] ?? 'AliExpress Product',
                    'slug' => $slug,
                    'description' => $productData['detail'] ?? null,
                    'selling_price' => $productData['promotion_price'] ?? $productData['original_price'] ?? null,
                    'cost_price' => $productData['original_price'] ?? null,
                    'currency' => 'USD',
                    'category_id' => $categoryId,
                    'is_active' => true,
                    'source_url' => $productData['item_url'] ?? null,
                    'supplier_product_url' => $productData['item_url'] ?? null,
                    'options' => $productData['sku_code'] ?? [],
                    'attributes' => [
                        'ali_item_id' => $productData['item_id'] ?? null,
                        'ali_category_id' => $productData['category_id'] ?? null,
                        'ali_shop_id' => $productData['shop_id'] ?? null,
                    ],
                    'seo_metadata' => [
                        'title' => $productData['subject'] ?? null,
                        'description' => substr($productData['detail'] ?? '', 0, 160),
                    ],
                ]
            );
            
            Log::info('AliExpress product imported', [
                'product_id' => $product->id,
                'ali_item_id' => $productData['item_id'] ?? null,
            ]);
            
            return $product;
        } catch (\Exception $e) {
            Log::error('Failed to map and save AliExpress product', [
                'product_data' => $productData,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

