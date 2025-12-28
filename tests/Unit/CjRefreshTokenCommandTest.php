<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CjRefreshTokenCommandTest extends TestCase
{
    public function test_refresh_command_succeeds_and_updates_cache(): void
    {
        Config::set('services.cj', [
            'api_key' => 'test-api-key',
            'base_url' => 'https://example.test',
            'timeout' => 5,
        ]);

        Http::fake([
            'https://example.test/*' => function ($request) {
                // Return a token for the authentication endpoint
                if (str_contains($request->url(), '/v1/authentication/getAccessToken')) {
                    return Http::response([
                        'result' => true,
                        'code' => 200,
                        'message' => 'OK',
                        'data' => ['accessToken' => 'refreshed-token', 'accessTokenExpiryDate' => now()->addHour()->toIsoString()],
                    ], 200);
                }

                return Http::response(['result' => true, 'code' => 200, 'message' => 'ok', 'data' => []], 200);
            },
        ]);

        $this->artisan('cj:refresh-token')->assertExitCode(0);

        $this->assertSame('refreshed-token', Cache::get('cj.access_token'));
    }

    public function test_refresh_command_handles_failure(): void
    {
        Config::set('services.cj', [
            'api_key' => 'test-api-key',
            'base_url' => 'https://example.test',
            'timeout' => 5,
        ]);

        Http::fake([
            'https://example.test/*' => Http::response('server error', 500),
        ]);

        $this->artisan('cj:refresh-token')->assertExitCode(1);
    }
}
