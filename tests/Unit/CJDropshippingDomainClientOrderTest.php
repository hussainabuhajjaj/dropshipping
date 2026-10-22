<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CJDropshippingDomainClientOrderTest extends TestCase
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

    public function test_it_calls_order_and_dispute_endpoints(): void
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

        $resp = $client->createOrder(['order' => ['items' => []]]);
        $this->assertTrue($resp->ok);
        $this->assertStringStartsWith('https://example.test/order/create', $captured->url());
        $this->assertSame('POST', strtoupper($captured->method()));

        $resp = $client->orderStatus(['orderId' => 'O1']);
        $this->assertTrue($resp->ok);
        $this->assertStringStartsWith('https://example.test/order/status', $captured->url());

        $resp = $client->getDisputeList(['pageNum' => 1, 'pageSize' => 10]);
        $this->assertTrue($resp->ok);
        $this->assertStringStartsWith('https://example.test/disputes/getDisputeList', $captured->url());

        $resp = $client->createDispute(['orderId' => 'O1', 'items' => []]);
        $this->assertTrue($resp->ok);
        $this->assertStringStartsWith('https://example.test/disputes/create', $captured->url());
    }
}

