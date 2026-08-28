<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\WooCommerce;

use App\Domain\WooCommerce\Models\WooCommerceProductMap;
use App\Domain\WooCommerce\Jobs\ImportWooCommerceProductJob;
use App\Domain\WooCommerce\Services\WooCommerceCustomerSyncService;
use App\Domain\WooCommerce\Services\WooCommerceLogService;
use App\Domain\WooCommerce\Services\WooCommerceProductSyncService;
use App\Services\AI\TranslationProvider;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WooCommerceSyncServiceTest extends TestCase
{
    private WooCommerceProductSyncService $productSyncService;

    private WooCommerceCustomerSyncService $customerSyncService;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('woocommerce', [
            'enabled' => true,
            'base_url' => 'https://test-store.com',
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
            'currency' => 'XOF',
            'webhook_secret' => 'wh_secret',
            'timeout' => 30,
            'retry_times' => 1,
            'retry_delay_ms' => 100,
            'verify_ssl' => false,
            'order_status_map' => [
                'pending' => 'pending',
                'paid' => 'processing',
                'completed' => 'completed',
            ],
            'webhook_status_map' => [
                'pending' => 'pending',
                'processing' => 'processing',
                'completed' => 'completed',
            ],
        ]);

        $this->productSyncService = app(WooCommerceProductSyncService::class);
        $this->customerSyncService = app(WooCommerceCustomerSyncService::class);
    }

    public function test_sync_product_creates_in_woocommerce(): void
    {
        $category = Category::factory()->create(['name' => 'Electronics']);
        $product = Product::factory()->create([
            'name' => 'New Product',
            'code' => 'NP-001',
            'selling_price' => 49.99,
            'status' => 'active',
            'category_id' => $category->id,
        ]);

        Http::fake([
            'test-store.com/wp-json/wc/v3/products/categories*' => Http::response([
                ['id' => 10, 'name' => 'Electronics', 'slug' => 'electronics'],
            ]),
            'test-store.com/wp-json/wc/v3/products' => Http::response([
                'id' => 789,
                'name' => 'New Product',
                'sku' => 'NP-001',
            ], 201),
        ]);

        $result = $this->productSyncService->syncProduct($product);

        $this->assertTrue($result->success);
        $this->assertSame(789, $result->woocommerceId);

        $this->assertDatabaseHas('woocommerce_product_maps', [
            'product_id' => $product->id,
            'woocommerce_product_id' => 789,
            'status' => 'synced',
        ]);
    }

    public function test_sync_product_updates_existing_mapping(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'name' => 'To Update',
            'code' => 'UPDATE-01',
            'selling_price' => 19.99,
            'status' => 'active',
            'category_id' => $category->id,
        ]);

        WooCommerceProductMap::create([
            'product_id' => $product->id,
            'woocommerce_product_id' => 500,
            'sku' => 'UPDATE-01',
            'status' => 'synced',
            'sync_hash' => hash('sha256', 'old-data'),
        ]);

        Http::fake([
            'test-store.com/wp-json/wc/v3/products/categories*' => Http::response([
                ['id' => 11, 'name' => $category->name, 'slug' => $category->slug],
            ]),
            'test-store.com/wp-json/wc/v3/products/500' => Http::response([
                'id' => 500,
                'name' => 'To Update',
                'sku' => 'UPDATE-01',
            ]),
        ]);

        $result = $this->productSyncService->syncProduct($product);

        $this->assertTrue($result->success);
    }

    public function test_sync_product_reuses_existing_woocommerce_category_when_term_exists(): void
    {
        $category = Category::factory()->create([
            'name' => 'Lunch Bags',
            'slug' => 'lunch-bags',
        ]);
        $product = Product::factory()->create([
            'name' => 'Thermal Lunch Bag',
            'code' => 'TLB-001',
            'selling_price' => 19.99,
            'status' => 'active',
            'category_id' => $category->id,
        ]);

        Http::fake(function ($request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/products/categories')) {
                return Http::response([]);
            }

            if ($request->method() === 'POST' && str_contains($request->url(), '/products/categories')) {
                return Http::response([
                    'code' => 'term_exists',
                    'message' => 'A term with the name provided already exists.',
                    'data' => [
                        'status' => 400,
                        'resource_id' => 123,
                    ],
                ], 400);
            }

            if ($request->method() === 'POST' && str_ends_with($request->url(), '/wp-json/wc/v3/products')) {
                return Http::response([
                    'id' => 900,
                    'name' => 'Thermal Lunch Bag',
                ], 201);
            }

            return Http::response([], 404);
        });

        $result = $this->productSyncService->syncProduct($product);

        $this->assertTrue($result->success);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/wp-json/wc/v3/products')
            && ($request->data()['categories'][0]['id'] ?? null) === 123);
    }

    public function test_sync_customer_creates_in_woocommerce(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response([]);
            }

            return Http::response([
                'id' => 888,
                'email' => 'test@example.com',
            ], 201);
        });

        $result = $this->customerSyncService->syncCustomer($customer);

        $this->assertTrue($result->success);
        $this->assertSame(888, $result->woocommerceId);

        $this->assertDatabaseHas('woocommerce_customer_maps', [
            'customer_id' => $customer->id,
            'woocommerce_customer_id' => 888,
            'email' => 'test@example.com',
        ]);
    }

    public function test_import_product_preserves_woocommerce_currency_and_does_not_treat_sale_price_as_cost(): void
    {
        Http::fake([
            'test-store.com/wp-json/wc/v3/products/321' => Http::response([
                'id' => 321,
                'name' => 'Discounted Bag',
                'slug' => 'discounted-bag',
                'sku' => 'BAG-321',
                'type' => 'simple',
                'status' => 'publish',
                'price' => '12000',
                'regular_price' => '15000',
                'sale_price' => '12000',
                'manage_stock' => true,
                'stock_quantity' => 7,
                'images' => [],
                'meta_data' => [
                    [
                        'key' => '_product_upload_source_url',
                        'value' => 'https://detail.1688.com/offer/952095514123.html',
                    ],
                ],
            ]),
        ]);

        $job = new ImportWooCommerceProductJob(321);
        $job->handle(app(\App\Domain\WooCommerce\Contracts\WooCommerceClientContract::class), app(WooCommerceLogService::class));

        $this->assertDatabaseHas('products', [
            'code' => 'BAG-321',
            'selling_price' => 12000,
            'cost_price' => null,
            'currency' => 'CNY',
            'supplier_currency' => 'CNY',
            'source_url' => 'https://detail.1688.com/offer/952095514123.html',
            'supplier_product_url' => 'https://detail.1688.com/offer/952095514123.html',
        ]);

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'BAG-321',
            'price' => 12000,
            'compare_at_price' => 15000,
            'cost_price' => null,
            'currency' => 'CNY',
            'supplier_currency' => 'CNY',
        ]);
    }

    public function test_import_product_translates_chinese_woocommerce_title_before_saving(): void
    {
        $this->app->bind(TranslationProvider::class, fn () => new class implements TranslationProvider {
            public function translate(string $text, string $source, string $target): string
            {
                return 'Portable Insulated Lunch Bag for Students and Office';
            }
        });

        Http::fake([
            'test-store.com/wp-json/wc/v3/products/4892' => Http::response([
                'id' => 4892,
                'name' => '手提保温便当包 便携棉质学生上班族饭盒袋 0001',
                'slug' => 'product-4892',
                'sku' => 'BAG-4892',
                'type' => 'simple',
                'status' => 'publish',
                'price' => '8.14',
                'images' => [],
                'meta_data' => [
                    [
                        'key' => '_product_upload_source_url',
                        'value' => 'https://detail.1688.com/offer/1058633468620.html',
                    ],
                ],
            ]),
        ]);

        $job = new ImportWooCommerceProductJob(4892);
        $job->handle(app(\App\Domain\WooCommerce\Contracts\WooCommerceClientContract::class), app(WooCommerceLogService::class));

        $this->assertDatabaseHas('products', [
            'code' => 'BAG-4892',
            'name' => 'Portable Insulated Lunch Bag for Students and Office',
            'currency' => 'CNY',
        ]);

        $product = \App\Domain\Products\Models\Product::query()->where('code', 'BAG-4892')->firstOrFail();

        $this->assertSame('手提保温便当包 便携棉质学生上班族饭盒袋 0001', $product->attributes['woocommerce_original_name'] ?? null);
    }
}
