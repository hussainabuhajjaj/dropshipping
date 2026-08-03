<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\WooCommerce;

use App\Domain\WooCommerce\Models\WooCommerceProductMap;
use App\Domain\WooCommerce\Services\WooCommerceCustomerSyncService;
use App\Domain\WooCommerce\Services\WooCommerceProductSyncService;
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
            'test-store.com/wp-json/wc/v3/products/categories' => Http::response([
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
            'test-store.com/wp-json/wc/v3/products/categories' => Http::response([
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

    public function test_sync_customer_creates_in_woocommerce(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        Http::fake([
            'test-store.com/wp-json/wc/v3/customers' => Http::response([
                'id' => 888,
                'email' => 'test@example.com',
            ], 201),
        ]);

        $result = $this->customerSyncService->syncCustomer($customer);

        $this->assertTrue($result->success);
        $this->assertSame(888, $result->woocommerceId);

        $this->assertDatabaseHas('woocommerce_customer_maps', [
            'customer_id' => $customer->id,
            'woocommerce_customer_id' => 888,
            'email' => 'test@example.com',
        ]);
    }
}
