<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CJDropshippingDomainClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.cj', [
            'app_id' => 'app-id',
            'api_key' => 'test-api-key',
            'api_secret' => 'secret',
            'base_url' => 'https://example.test',
            'timeout' => 5,
        ]);
    }

    public function test_it_hits_order_and_cart_endpoints(): void
    {
        $captured = null;

        Http::fake([
            '*' => function ($request) use (&$captured) {
                $captured = $request;
                return Http::response([
                    'result' => true,
                    'code' => 200,
                    'message' => 'Success',
                    'data' => [],
                ], 200);
            },
        ]);

        $client = CJDropshippingClient::fromConfig();

        /** @var ApiResponse $resp */
        $resp = $client->listOrders(['pageNum' => 1, 'pageSize' => 10]);
        $this->assertTrue($resp->ok);
        $this->assertStringStartsWith('https://example.test/shopping/order/list', $captured->url());
        $this->assertSame('GET', strtoupper($captured->method()));

        $resp = $client->getOrderDetail(['orderId' => 'O1']);
        $this->assertTrue($resp->ok);
        $this->assertStringStartsWith('https://example.test/shopping/order/getOrderDetail', $captured->url());

        $resp = $client->createOrder(['order' => ['items' => []]]);
        $this->assertTrue($resp->ok);
        $this->assertStringStartsWith('https://example.test/order/create', $captured->url());

        $resp = $client->addCart(['cjOrderIdList' => ['o1','o2']]);
        $this->assertTrue($resp->ok);
        $this->assertStringStartsWith('https://example.test/shopping/order/addCart', $captured->url());
    }

    public function test_it_hits_search_and_product_detail_endpoints(): void
    {
        $captured = null;

        Http::fake([
            '*' => function ($request) use (&$captured) {
                $captured = $request;
                return Http::response([
                    'result' => true,
                    'code' => 200,
                    'message' => 'Success',
                    'data' => [],
                ], 200);
            },
        ]);

        $client = CJDropshippingClient::fromConfig();

        $resp = $client->searchProducts(['keyword' => 'shirt', 'pageNum' => 1, 'pageSize' => 10]);
        $this->assertTrue($resp->ok);
        $this->assertStringStartsWith('https://example.test/product/search', $captured->url());
        $this->assertSame('POST', strtoupper($captured->method()));

        $resp = $client->productDetail('PID999');
        $this->assertTrue($resp->ok);
        $this->assertStringStartsWith('https://example.test/product/detail', $captured->url());
        $this->assertSame('POST', strtoupper($captured->method()));

        $resp = $client->getPriceByPid('PID123');
        $this->assertTrue($resp->ok);
        $this->assertStringStartsWith('https://example.test/v1/product/price/queryByPid', $captured->url());
        $this->assertSame('GET', strtoupper($captured->method()));

        $resp = $client->getPriceBySku('SKU123');
        $this->assertTrue($resp->ok);
        $this->assertStringStartsWith('https://example.test/v1/product/price/queryBySku', $captured->url());
        $this->assertSame('GET', strtoupper($captured->method()));

        $resp = $client->getPriceByVid('VID123');
        $this->assertTrue($resp->ok);
        $this->assertStringStartsWith('https://example.test/v1/product/price/queryByVid', $captured->url());
        $this->assertSame('GET', strtoupper($captured->method()));

        $resp = $client->searchVariants(['pid' => 'PID123']);
        $this->assertTrue($resp->ok);
        $this->assertStringStartsWith('https://example.test/v1/product/variant/search', $captured->url());
        $this->assertSame('POST', strtoupper($captured->method()));
    }
}

