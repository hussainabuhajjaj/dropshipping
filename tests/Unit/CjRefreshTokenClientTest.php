<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CjRefreshTokenClientTest extends TestCase
{
    public function test_get_access_token_handles_transient_failures_then_succeeds(): void
    {
        Config::set('services.cj', [
            'api_key' => 'test-api-key',
            'base_url' => 'https://example.test',
            'timeout' => 5,
        ]);

        $seq = 0;
        Http::fake([
            'https://example.test/*' => function ($request) use (&$seq) {
                $seq++;
                if ($seq === 1) {
                    return Http::response('server error', 500);
                }

                if (str_contains($request->url(), '/v1/authentication/getAccessToken')) {
                    return Http::response([
                        'result' => true,
                        'code' => 200,
                        'message' => 'OK',
                        'data' => ['accessToken' => 'token-123', 'accessTokenExpiryDate' => now()->addHour()->toIsoString()],
                    ], 200);
                }

                return Http::response(['result' => true, 'code' => 200, 'message' => 'ok', 'data' => []], 200);
            },
        ]);

        $client = app(CJDropshippingClient::class);
        $token = $client->getAccessToken(true);
        $this->assertSame('token-123', $token);
        $this->assertSame('token-123', Cache::get('cj.access_token'));
    }
}
