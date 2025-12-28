<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiExceptionRequestIdTest extends TestCase
{
    public function test_api_exception_includes_request_id(): void
    {
        Config::set('services.cj', [
            'app_id' => 'a',
            'api_key' => 'k',
            'api_secret' => 's',
            'base_url' => 'https://example.test',
            'timeout' => 5,
        ]);

        Http::fake([
            'https://example.test/*' => Http::response([
                'result' => false,
                'code' => 1600101,
                'message' => 'Interface not found',
                'requestId' => 'R-12345',
            ], 400),
        ]);

        $client = CJDropshippingClient::fromConfig();

        $this->expectException(ApiException::class);

        try {
            $client->listProducts(['pageNum' => 1, 'pageSize' => 1]);
        } catch (ApiException $e) {
            $this->assertSame('R-12345', $e->requestId);
            throw $e;
        }
    }
}
