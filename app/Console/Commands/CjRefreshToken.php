<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CjRefreshToken extends Command
{
    protected $signature = 'cj:refresh-token';
    protected $description = 'Refresh CJ access token and write to cache (no-op if token is valid).';

    public function handle(): int
    {
        try {
            $client = app(CJDropshippingClient::class);

            // Force refresh and persist into cache via client logic
            $token = $client->getAccessToken(true);

            Cache::put('cj.access_token', $token, 3600 * 24 * 10); // fallback TTL
            $this->info('CJ access token refreshed.');
            Log::info('CJ access token refreshed via artisan command');

            return 0;
        } catch (\Throwable $e) {
            $this->error('Failed to refresh CJ access token: ' . $e->getMessage());
            Log::error('CJ token refresh failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return 1;
        }
    }
}
