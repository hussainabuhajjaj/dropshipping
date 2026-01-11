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
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $this->timeout = (int) ($config['timeout'] ?? 10);

        if (! $this->apiKey) {
            throw new RuntimeException('CJ API key is not configured.');
        }

        $this->client = new ApiClient($this->baseUrl, [], $this->timeout);
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
        // Adjust endpoint if your API docs specify differently
        $response = $client->post('/v1/shopping/order/getOrderStatus', $payload);
        // Optionally, you can return $response->data or the full response
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
        // You may need to adjust the endpoint or payload according to CJ API docs
        return $this->client->post('/v1/freight/calculate', $payload);
    }
   
public function getAccessToken()
    {
        $setting = new Setting();
        if ($access_token = $setting->valueOf('cj_access_token', null)) {
            $access_token_expired_at = $setting->valueOf('cj_access_expiry', null);
            if ($access_token_expired_at < now()) {
                return $access_token;
            }
        }
        return $this->generateNewToken();
    }


    public function generateNewToken()
    {
        $response = Http::timeout(300)
            ->retry(2, sleepMilliseconds: 200)
            ->post($this->baseUrl . '/v1/authentication/getAccessToken', [
                'apiKey' => $this->apiKey, // Only apiKey is required per docs
            ]);

        $body = $response->json();
        $data = $body['data'];
        // dd($body);
        $Cjbody = [
            'cj_access_token' => $data['accessToken'],
            'cj_access_expiry' => $this->parseDate($data['accessTokenExpiryDate'] ?? null),
            'cj_refresh_token' => $data['refreshToken'] ?? null,
            'cj_refresh_expiry' => isset($data['refreshTokenExpiryDate']) ?
                $this->parseDate($data['refreshTokenExpiryDate']) : null,
            'cj_open_id' => $data['openId'] ?? null,
            'cj_created_at' => now()->timestamp,
        ];

        Setting::setSetting($Cjbody);
        return $Cjbody['cj_access_token'];
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

    public function getProductReviews(string $pid, int $pageNum = 1, int $pageSize = 20): ApiResponse
    {
        return $this->products()->getProductReviews($pid, $pageNum, $pageSize);
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
        if (! $date) {
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
