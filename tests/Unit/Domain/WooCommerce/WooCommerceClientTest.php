<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\WooCommerce;

use App\Infrastructure\WooCommerce\WooCommerceApiException;
use App\Infrastructure\WooCommerce\WooCommerceClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WooCommerceClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('woocommerce', [
            'enabled' => true,
            'base_url' => 'https://test-store.com',
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
            'timeout' => 30,
            'retry_times' => 1,
            'retry_delay_ms' => 100,
            'verify_ssl' => false,
        ]);
    }

    public function test_get_product_returns_mapped_data(): void
    {
        Http::fake([
            'test-store.com/wp-json/wc/v3/products/1' => Http::response([
                'id' => 1,
                'name' => 'Test Product',
                'sku' => 'SKU-001',
                'type' => 'simple',
                'status' => 'publish',
                'regular_price' => '29.99',
                'manage_stock' => true,
                'stock_quantity' => 100,
                'categories' => [['id' => 5, 'name' => 'Electronics']],
            ]),
        ]);

        $client = app(WooCommerceClient::class);
        $product = $client->getProduct(1);

        $this->assertSame(1, $product->woocommerceId);
        $this->assertSame('Test Product', $product->name);
        $this->assertSame('SKU-001', $product->sku);
        $this->assertSame(29.99, $product->regularPrice);
        $this->assertSame(100, $product->stockQuantity);
    }

    public function test_get_customer_by_email_returns_null_when_not_found(): void
    {
        Http::fake([
            'test-store.com/wp-json/wc/v3/customers*' => Http::response([]),
        ]);

        $client = app(WooCommerceClient::class);
        $customer = $client->getCustomerByEmail('nonexistent@example.com');

        $this->assertNull($customer);
    }

    public function test_create_order_returns_order_data(): void
    {
        Http::fake([
            'test-store.com/wp-json/wc/v3/orders' => Http::response([
                'id' => 456,
                'number' => 'WC-456',
                'status' => 'pending',
                'total' => '99.99',
            ], 201),
        ]);

        $client = app(WooCommerceClient::class);
        $result = $client->createOrder([
            'payment_method' => 'stripe',
            'line_items' => [['product_id' => 1, 'quantity' => 2]],
        ]);

        $this->assertSame(456, $result['id']);
        $this->assertSame('WC-456', $result['number']);
    }

    public function test_rate_limit_throws_exception(): void
    {
        Http::fake([
            'test-store.com/wp-json/wc/v3/products' => Http::response([], 429, ['Retry-After' => '1']),
        ]);

        $client = app(WooCommerceClient::class);

        try {
            $client->getProducts();
            $this->fail('Expected WooCommerceApiException was not thrown.');
        } catch (WooCommerceApiException $e) {
            $this->assertTrue($e->isRateLimit());
            $this->assertSame(429, $e->getStatusCode());
        }
    }

    public function test_api_error_throws_woocommerce_api_exception(): void
    {
        Http::fake([
            'test-store.com/wp-json/wc/v3/products/999' => Http::response([
                'code' => 'woocommerce_rest_product_invalid',
                'message' => 'Product does not exist',
            ], 404),
        ]);

        $client = app(WooCommerceClient::class);

        $this->expectException(WooCommerceApiException::class);
        $this->expectExceptionCode(404);

        $client->getProduct(999);
    }

    public function test_connection_test_returns_false_on_failure(): void
    {
        Http::fake([
            'test-store.com/wp-json/wc/v3/products*' => Http::response([], 500),
        ]);

        $client = app(WooCommerceClient::class);
        $this->assertFalse($client->testConnection());
    }

    public function test_add_shipment_tracking(): void
    {
        Http::fake([
            'test-store.com/wp-json/wc/v3/orders/1/shipment-trackings' => Http::response([
                'id' => 789,
                'tracking_number' => 'TRACK-123',
                'carrier' => 'UPS',
            ], 201),
        ]);

        $client = app(WooCommerceClient::class);
        $result = $client->addShipmentTracking(1, [
            'tracking_number' => 'TRACK-123',
            'carrier' => 'UPS',
        ]);

        $this->assertSame('TRACK-123', $result['tracking_number']);
    }
}
