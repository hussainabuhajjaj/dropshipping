<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\WooCommerce;

use App\Infrastructure\WooCommerce\WooCommerceApiException;
use App\Infrastructure\WooCommerce\WooCommerceClient;
use App\Services\AI\TranslationProvider;
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
            'currency' => 'XOF',
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
                'price' => '24.99',
                'regular_price' => '29.99',
                'sale_price' => '24.99',
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
        $this->assertSame(24.99, $product->price);
        $this->assertSame(29.99, $product->regularPrice);
        $this->assertSame(24.99, $product->activePrice());
        $this->assertSame(29.99, $product->compareAtPrice());
        $this->assertSame('XOF', $product->currency);
        $this->assertSame(100, $product->stockQuantity);
    }

    public function test_product_active_price_falls_back_when_current_price_is_zero(): void
    {
        Http::fake([
            'test-store.com/wp-json/wc/v3/products/2' => Http::response([
                'id' => 2,
                'name' => 'Variable Parent',
                'sku' => 'VP-002',
                'type' => 'variable',
                'status' => 'publish',
                'price' => '0',
                'regular_price' => '12000',
                'manage_stock' => false,
            ]),
        ]);

        $client = app(WooCommerceClient::class);
        $product = $client->getProduct(2);

        $this->assertSame(12000.0, $product->activePrice());
    }

    public function test_product_from_1688_metadata_resolves_to_cny(): void
    {
        Http::fake([
            'test-store.com/wp-json/wc/v3/products/4907' => Http::response([
                'id' => 4907,
                'name' => 'Insulated Picnic Cooler Bag 66195 Large Organizer Tote',
                'type' => 'simple',
                'status' => 'publish',
                'price' => '41.00',
                'meta_data' => [
                    [
                        'key' => '_product_upload_source_url',
                        'value' => 'https://detail.1688.com/offer/952095514123.html',
                    ],
                ],
            ]),
        ]);

        $client = app(WooCommerceClient::class);
        $product = $client->getProduct(4907);

        $this->assertSame(41.0, $product->activePrice());
        $this->assertSame('CNY', $product->currency);
    }

    public function test_chinese_product_name_can_resolve_to_english_import_name(): void
    {
        Http::fake([
            'test-store.com/wp-json/wc/v3/products/4892' => Http::response([
                'id' => 4892,
                'name' => '手提保温便当包 便携棉质学生上班族饭盒袋 0001',
                'type' => 'simple',
                'status' => 'publish',
                'price' => '8.14',
            ]),
        ]);

        $translator = new class implements TranslationProvider {
            public function translate(string $text, string $source, string $target): string
            {
                return 'Portable Insulated Lunch Bag for Students and Office';
            }
        };

        $client = app(WooCommerceClient::class);
        $product = $client->getProduct(4892);

        $this->assertTrue($product->hasNonEnglishName());
        $this->assertSame('Portable Insulated Lunch Bag for Students and Office', $product->importName($translator));
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
