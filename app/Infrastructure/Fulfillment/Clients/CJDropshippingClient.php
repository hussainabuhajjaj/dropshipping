<?php

declare(strict_types=1);

namespace App\Infrastructure\Fulfillment\Clients;

use App\Services\Api\ApiClient;
use App\Services\Api\ApiException;
use App\Services\Api\ApiResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use App\Infrastructure\Fulfillment\Clients\CJ\CjAuthApi;
use App\Infrastructure\Fulfillment\Clients\CJ\CjProductApi;
use App\Infrastructure\Fulfillment\Clients\CJ\CjSettingsApi;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CJDropshippingClient

{

    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private ApiClient $client;
    private ?CjAuthApi $authApi = null;
    private ?CjSettingsApi $settingsApi = null;
    private ?CjProductApi $productApi = null;

    public function __construct()
    {
        $config = config('services.cj', []);
        $this->apiKey = (string)($config['api_key'] ?? '');
        $this->baseUrl = rtrim((string)($config['base_url'] ?? ''), '/');
        $this->timeout = (int)($config['timeout'] ?? 10);

        if (!$this->apiKey) {
            throw new RuntimeException('CJ API key is not configured.');
        }

        $headers = [];
        $platformToken = (string)($config['platform_token'] ?? '');
        if ($platformToken !== '') {
            // Optional header: only sent when configured.
            $headers['CJ-Platform-Token'] = $platformToken;
        }

        $this->client = new ApiClient($this->baseUrl, $headers, $this->timeout, retryTimes: 4, retryDelayMs: 800);
    }


    /**
     * Query order status from CJ Dropshipping API.
     * Endpoint: /v1/shopping/order/getOrderStatus (POST)
     * @param array $payload Should contain 'orderIds' => [array of order IDs]
     * @return ApiResponse|array
     */
    public function orderStatus(array $payload)
    {
        $client = $this->authClient();
        // CJ API expects orderIds as an array in the payload
        $response = $client->post('/v1/shopping/order/getOrderStatus', $payload);
        // Optionally, you can return $response->data or the full response
        return $response;
    }
    public function getOrderList(array $payload){
            $client = $this->authClient();
            $response = $client->get('/v1/shopping/order/list', $payload);
            return $response;
            }

    /**
     * Calculate freight/shipping cost using CJ API.
     * Endpoint: /v1/freight/calculate (POST)
     * @param array $payload
     * @return ApiResponse
     */
    public function freightCalculate(array $payload): ApiResponse
    {
        $client = $this->authClient();
        // You may need to adjust the endpoint or payload according to CJ API docs
        return $client->post('/v1/logistic/freightCalculate', $payload);
    }

    public function getAccessToken(bool $forceRefresh = false): string
    {
        if ($forceRefresh) {
            return $this->generateNewToken();
        }

        // Prefer cache (fast path).
        $cachedToken = Cache::get('cj.access_token');
        if (is_string($cachedToken) && $cachedToken !== '') {
            $cachedExpiry = Cache::get('cj.access_expiry');
            $cachedExpiryTs = is_numeric($cachedExpiry) ? (int) $cachedExpiry : null;

            // If expiry missing, treat as valid (caller provided fallback TTL).
            if ($cachedExpiryTs === null || $cachedExpiryTs > (time() + 60)) {
                return $cachedToken;
            }
        }

        $setting = new Setting();
        $accessToken = $setting->valueOf('cj_access_token', null);
        if (is_string($accessToken) && $accessToken !== '') {
            $expiresAt = $setting->valueOf('cj_access_expiry', null);
            $expiresAtTimestamp = is_numeric($expiresAt) ? (int) $expiresAt : null;

            // Refresh 60s early.
            if ($expiresAtTimestamp === null || $expiresAtTimestamp > (time() + 60)) {
                $this->cacheAccessToken($accessToken, $expiresAtTimestamp);
                return $accessToken;
            }
        }

        return $this->generateNewToken();
    }


    public function generateNewToken(): string
    {
        $response = Http::timeout(300)
            ->retry(2, sleepMilliseconds: 200)
            ->post($this->baseUrl . '/v1/authentication/getAccessToken', [
                'apiKey' => $this->apiKey, // Only apiKey is required per docs
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('CJ token request failed: HTTP ' . $response->status());
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException('CJ token request failed: invalid JSON response');
        }

        // CJ-style schema: { code, result, message, data }
        if (array_key_exists('result', $body) && array_key_exists('code', $body)) {
            $ok = (bool) $body['result'] && ((int) $body['code'] === 200);
            if (! $ok) {
                $message = is_string($body['message'] ?? null) ? $body['message'] : 'CJ token request failed';
                throw new RuntimeException($message);
            }
        }

        $data = $body['data'] ?? null;
        if (! is_array($data) || ! is_string($data['accessToken'] ?? null) || $data['accessToken'] === '') {
            throw new RuntimeException('CJ token request failed: missing accessToken');
        }

        $expiresAtTimestamp = $this->parseDate($data['accessTokenExpiryDate'] ?? null);
        $Cjbody = [
            'cj_access_token' => $data['accessToken'],
            'cj_access_expiry' => $expiresAtTimestamp,
            'cj_refresh_token' => $data['refreshToken'] ?? null,
            'cj_refresh_expiry' => isset($data['refreshTokenExpiryDate']) ?
                $this->parseDate($data['refreshTokenExpiryDate']) : null,
            'cj_open_id' => $data['openId'] ?? null,
            'cj_created_at' => now()->timestamp,
        ];

        Setting::setSetting($Cjbody);
        $this->cacheAccessToken($Cjbody['cj_access_token'], $expiresAtTimestamp);
        return $Cjbody['cj_access_token'];
    }

    private function cacheAccessToken(string $token, ?int $expiresAtTimestamp): void
    {
        $ttlSeconds = 3600 * 24 * 10; // fallback

        if (is_int($expiresAtTimestamp) && $expiresAtTimestamp > 0) {
            $ttlSeconds = max(60, $expiresAtTimestamp - time());
            Cache::put('cj.access_expiry', $expiresAtTimestamp, $ttlSeconds);
        } else {
            Cache::forget('cj.access_expiry');
        }

        Cache::put('cj.access_token', $token, $ttlSeconds);
    }

    public function parseDate(?string $dateString): int
    {
        if (!$dateString) {
            // Default fallback: access token 15 days, refresh token 180 days from now
            return time() + (15 * 86400); // 15 days
        }

        try {
            // Try to parse the date string (e.g., "2021-08-18T09:16:33+08:00")
            return \Carbon\Carbon::parse($dateString)->timestamp;
        } catch (\Exception $e) {
            Log::warning('[CJ Auth] Failed to parse date, using defaults', [
                'date_string' => $dateString,
                'error' => $e->getMessage()
            ]);

            // Default fallback based on the type of date
            if (str_contains($dateString, 'accessTokenExpiryDate') || str_contains($dateString, 'access')) {
                return time() + (15 * 86400); // 15 days
            }
            return time() + (180 * 86400); // 180 days
        }
    }

    public function logout(): ApiResponse
    {
        return $this->auth()->logout();
    }

    public function getSettings(): ApiResponse
    {
        return $this->settings()->getSettings();
    }

    public function updateAccount(?string $openName = null, ?string $openEmail = null): ApiResponse
    {
        return $this->settings()->updateAccount($openName, $openEmail);
    }

    public function getProduct(string $pid): ApiResponse
    {
        return $this->products()->getProduct($pid);
    }

    /**
     * Create order using v2 endpoint (with auth token)
     */
    public function createOrderV2(array $payload): ApiResponse
    {
        $client = $this->authClient();
        return $client->post('/v1/shopping/order/createOrderV2', $payload);
    }

    /**
     * Create order V3 (updated endpoint, with auth token)
     */
    public function createOrderV3(array $payload): ApiResponse
    {
        $client = $this->authClient();
        return $client->post('/v1/shopping/order/createOrderV3', $payload);
    }

    public function getProductBy(array $criteria): ApiResponse
    {
        return $this->products()->getProductBy($criteria);
    }

    public function listProducts(array $filters = []): ApiResponse
    {
        return $this->products()->listProducts($filters);
    }

    public function listProductsV2(array $filters = []): ApiResponse
    {
        return $this->products()->listProductsV2($filters);
    }

    public function searchProducts(array $filters): ApiResponse
    {
        return $this->products()->searchProducts($filters);
    }

    public function productDetail(string $pid): ApiResponse
    {
        return $this->products()->productDetail($pid);
    }

    public function getPriceByPid(string $pid): ApiResponse
    {
        return $this->products()->getPriceByPid($pid);
    }

    public function getPriceBySku(string $sku): ApiResponse
    {
        return $this->products()->getPriceBySku($sku);
    }

    public function listGlobalWarehouses(): ApiResponse
    {
        return $this->products()->listGlobalWarehouses();
    }

    public function getWarehouseDetail(string $id): ApiResponse
    {
        return $this->products()->getWarehouseDetail($id);
    }

    public function listCategories(): ApiResponse
    {
        return $this->products()->listCategories();
    }

    public function getVariantsByPid(string $pid): ApiResponse
    {
        return $this->products()->getVariantsByPid($pid);
    }

    public function getVariantByVid(string $vid): ApiResponse
    {
        return $this->products()->getVariantByVid($vid);
    }

    public function getStockByVid(string $vid): ApiResponse
    {
        return $this->products()->getStockByVid($vid);
    }

    public function getStockBySku(string $sku): ApiResponse
    {
        return $this->products()->getStockBySku($sku);
    }

    public function getStockByPid(string $pid): ApiResponse
    {
        return $this->products()->getStockByPid($pid);
    }

    public function getPriceByVid(string $vid): ApiResponse
    {
        return $this->products()->getPriceByVid($vid);
    }

    public function searchVariants(array $filters = []): ApiResponse
    {
        return $this->products()->searchVariants($filters);
    }

    public function getProductReviews(string $pid, int $pageNum = 1, int $pageSize = 20, ?int $score = null): ApiResponse
    {
        return $this->products()->getProductReviews($pid, $pageNum, $pageSize, $score);
    }

    public function createSourcing(string $productUrl, ?string $note = null, ?string $sourceId = null): ApiResponse
    {
        return $this->products()->createSourcing($productUrl, $note, $sourceId);
    }

    public function querySourcing(?string $sourcingId = null, int $pageNum = 1, int $pageSize = 20): ApiResponse
    {
        return $this->products()->querySourcing($sourcingId, $pageNum, $pageSize);
    }

    public function addToMyProducts(string $pid): ApiResponse
    {
        return $this->products()->addToMyProducts($pid);
    }

    public function listMyProducts(array $filters = []): ApiResponse
    {
        return $this->products()->listMyProducts($filters);
    }

    public function withToken(): string
    {
        return $this->getAccessToken();
    }

    public function authClient(): ApiClient
    {
        $token = $this->getAccessToken();
        return $this->client->withToken($token, 'CJ-Access-Token');
    }

    public function auth(): CjAuthApi
    {
        return $this->authApi ??= new CjAuthApi($this);
    }

    public function settings(): CjSettingsApi
    {
        return $this->settingsApi ??= new CjSettingsApi($this);
    }

    public function products(): CjProductApi
    {
        return $this->productApi ??= new CjProductApi($this);
    }

    private function ttlFromDate(?string $date, int $fallbackSeconds): int
    {
        if (!$date) {
            return $fallbackSeconds;
        }

        try {
            $expiresAt = Carbon::parse($date);
            $seconds = $expiresAt->diffInSeconds(now(), false);
            return $seconds > 0 ? $seconds : $fallbackSeconds;
        } catch (\Exception) {
            return $fallbackSeconds;
        }
    }
}
