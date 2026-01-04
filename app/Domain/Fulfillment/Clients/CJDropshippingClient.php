<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Clients;

use App\Services\Api\ApiException;
use App\Services\Api\ApiResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CJDropshippingClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $appId,
        private readonly string $apiKey,
        private readonly string $apiSecret = '',
        private readonly ?string $platformToken = null,
        private readonly int $timeoutSeconds = 10,
    ) {
    }

    public static function fromConfig(): self
    {
        $cfg = config('services.cj');

        $instance = new self(
            baseUrl: rtrim((string) ($cfg['base_url'] ?? ''), '/'),
            appId: (string) ($cfg['app_id'] ?? ''),
            apiKey: (string) ($cfg['api_key'] ?? ''),
            apiSecret: (string) ($cfg['api_secret'] ?? ''),
            platformToken: $cfg['platform_token'] ?? null,
            timeoutSeconds: (int) ($cfg['timeout'] ?? 10),
        );

        $instance->assertConfigured();

        return $instance;
    }

    public function request(string $method, string $path, array $payload = []): ApiResponse
    {
        $isGet = strtoupper($method) === 'GET';
        $body = $isGet ? '' : ($payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
        $timestamp = (int) (microtime(true) * 1000);
        $signature = $this->sign($timestamp, $body);

        $headers = [
            'CJ-APIKEY' => $this->apiKey,
            'CJ-APPID' => $this->appId,
            'CJ-TIMESTAMP' => (string) $timestamp,
            'CJ-SIGN' => $signature,
        ];

        if ($this->platformToken) {
            $headers['platformToken'] = $this->platformToken;
        }

        $request = $this->http()->withHeaders($headers);

        try {
            $response = $isGet
                ? $request->send($method, $this->baseUrl . '/' . ltrim($path, '/'), ['query' => $payload])
                : $request->withBody($body, 'application/json')->send($method, $this->baseUrl . '/' . ltrim($path, '/'));

            return $this->buildResponse($response->body(), $response->status());
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $res = $e->response;
            $raw = $res ? $res->body() : null;
            $status = $res ? $res->status() : 0;

            $decoded = null;
            try {
                $decoded = json_decode((string) $raw, true);
            } catch (\Throwable $_) {
                // ignore
            }

            $requestId = is_array($decoded) && array_key_exists('requestId', $decoded) ? (string) $decoded['requestId'] : null;
            $message = is_array($decoded) && array_key_exists('message', $decoded) ? (string) $decoded['message'] : $e->getMessage();
            $codeString = is_array($decoded) && array_key_exists('code', $decoded) ? (string) $decoded['code'] : null;

            $apiEx = new ApiException($message ?: 'HTTP request failed', $status, $codeString, $decoded ?? $raw, $requestId);

            // Send alerts for server-side or rate-limit errors
            if ($apiEx->status >= 500 || $apiEx->status === 429) {
                \App\Infrastructure\Fulfillment\Clients\CJ\CjAlertService::alert('CJ API error', [
                    'status' => $apiEx->status,
                    'code' => $apiEx->codeString,
                    'requestId' => $apiEx->requestId,
                    'body' => $apiEx->body,
                ]);
            }

            throw $apiEx;
        } catch (\App\Services\Api\ApiException $e) {
            // Send alerts for server-side or repeated errors
            if ($e->status >= 500 || $e->status === 429) {
                \App\Infrastructure\Fulfillment\Clients\CJ\CjAlertService::alert('CJ API error', [
                    'status' => $e->status,
                    'code' => $e->codeString,
                    'requestId' => $e->requestId,
                    'body' => $e->body,
                ]);
            }

            throw $e;
        }
    }

    public function searchProducts(array $filters): ApiResponse
    {
        return $this->request('POST', '/product/search', $filters);
    }

    public function listProducts(array $filters = []): ApiResponse
    {
        return $this->request('GET', '/product/list', $filters);
    }

    public function productDetail(string $productId): ApiResponse
    {
        return $this->request('POST', '/product/detail', ['pid' => $productId]);
    }

    public function freightQuote(array $payload): ApiResponse
    {
        return $this->request('POST', '/freight/calculate', $payload);
    }

    public function createOrder(array $payload): ApiResponse
    {
        return $this->request('POST', '/order/create', $payload);
    }

    public function orderStatus(array $payload): ApiResponse
    {
        return $this->request('POST', '/order/status', $payload);
    }

    public function orderDetail(array $payload): ApiResponse
    {
        return $this->request('POST', '/order/detail', $payload);
    }

    public function track(array $payload): ApiResponse
    {
        return $this->request('POST', '/logistics/track', $payload);
    }

    /**
     * List disputed products for an order.
     */
    public function disputeProducts(array $filters): ApiResponse
    {
        return $this->request('GET', '/disputes/disputeProducts', $filters);
    }

    /**
     * Confirm dispute info before creation.
     */
    public function disputeConfirmInfo(array $payload): ApiResponse
    {
        return $this->request('POST', '/disputes/disputeConfirmInfo', $payload);
    }

    /**
     * Create a dispute.
     */
    public function createDispute(array $payload): ApiResponse
    {
        return $this->request('POST', '/disputes/create', $payload);
    }

    /**
     * Cancel a dispute.
     */
    public function cancelDispute(array $payload): ApiResponse
    {
        return $this->request('POST', '/disputes/cancel', $payload);
    }

    /**
     * Get dispute list.
     */
    public function getDisputeList(array $filters = []): ApiResponse
    {
        return $this->request('GET', '/disputes/getDisputeList', $filters);
    }

    /**
     * Configure webhooks for product/stock/order/logistics events.
     */
    public function setWebhook(array $payload): ApiResponse
    {
        return $this->request('POST', '/webhook/set', $payload);
    }

    /**
     * Freight calculation (logistic/freightCalculate).
     */
    public function freightCalculate(array $payload): ApiResponse
    {
        return $this->request('POST', '/logistic/freightCalculate', $payload);
    }

    /**
     * Freight calculation tip (logistic/freightCalculateTip).
     */
    public function freightCalculateTip(array $payload): ApiResponse
    {
        return $this->request('POST', '/logistic/freightCalculateTip', $payload);
    }

    /**
     * Get tracking information (new endpoint).
     */
    public function trackInfo(array $payload): ApiResponse
    {
        return $this->request('GET', '/logistic/trackInfo', $payload);
    }

    /**
     * Get tracking information (deprecated endpoint).
     */
    public function getTrackInfo(array $payload): ApiResponse
    {
        return $this->request('GET', '/logistic/getTrackInfo', $payload);
    }

    /**
     * Get storage / warehouse info.
     */
    public function warehouseDetail(string $id): ApiResponse
    {
        return $this->request('GET', '/warehouse/detail', ['id' => $id]);
    }

    /**
     * Create order V2 (payType controls balance vs. no balance).
     */
    public function createOrderV2(array $payload): ApiResponse
    {
        return $this->request('POST', '/shopping/order/createOrderV2', $payload);
    }

    /**
     * Create order V3 (updated endpoint).
     */
    public function createOrderV3(array $payload): ApiResponse
    {
        return $this->request('POST', '/shopping/order/createOrderV3', $payload);
    }

    /**
     * Add cart for CJ orders.
     */
    public function addCart(array $cjOrderIds): ApiResponse
    {
        return $this->request('POST', '/shopping/order/addCart', ['cjOrderIdList' => $cjOrderIds]);
    }

    /**
     * Confirm add cart for CJ orders.
     */
    public function addCartConfirm(array $cjOrderIds): ApiResponse
    {
        return $this->request('POST', '/shopping/order/addCartConfirm', ['cjOrderIdList' => $cjOrderIds]);
    }

    /**
     * Save and generate parent order for shipments.
     */
    public function saveGenerateParentOrder(string $shipmentOrderId): ApiResponse
    {
        return $this->request('POST', '/shopping/order/saveGenerateParentOrder', ['shipmentOrderId' => $shipmentOrderId]);
    }

    /**
     * List CJ orders.
     */
    public function listOrders(array $filters = []): ApiResponse
    {
        return $this->request('GET', '/shopping/order/list', $filters);
    }

    /**
     * Get CJ order detail.
     */
    public function getOrderDetail(array $filters): ApiResponse
    {
        return $this->request('GET', '/shopping/order/getOrderDetail', $filters);
    }

    /**
     * Delete CJ order.
     */
    public function deleteOrder(string $orderId): ApiResponse
    {
        return $this->request('DELETE', '/shopping/order/deleteOrder', ['orderId' => $orderId]);
    }

    /**
     * Confirm CJ order.
     */
    public function confirmOrder(string $orderId): ApiResponse
    {
        return $this->request('PATCH', '/shopping/order/confirmOrder', ['orderId' => $orderId]);
    }

    /**
     * Change order warehouse.
     */
    public function changeWarehouse(string $orderCode, string $storageId): ApiResponse
    {
        return $this->request('POST', '/shopping/order/changeWarehouse', [
            'orderCode' => $orderCode,
            'storageId' => $storageId,
        ]);
    }

    /**
     * Get balance.
     */
    public function getBalance(): ApiResponse
    {
        return $this->request('GET', '/shopping/pay/getBalance');
    }

    /**
     * Pay balance for an order.
     */
    public function payBalance(string $orderId): ApiResponse
    {
        return $this->request('POST', '/shopping/pay/payBalance', ['orderId' => $orderId]);
    }

    /**
     * Pay balance v2 for shipment order.
     */
    public function payBalanceV2(string $shipmentOrderId, string $payId): ApiResponse
    {
        return $this->request('POST', '/shopping/pay/payBalanceV2', [
            'shipmentOrderId' => $shipmentOrderId,
            'payId' => $payId,
        ]);
    }

    /**
     * Upload shipping/waybill info (multipart).
     */
    public function uploadWaybillInfo(array $payload): ApiResponse
    {
        return $this->multipartRequest('/shopping/order/uploadWaybillInfo', $payload);
    }

    /**
     * Update shipping/waybill info (multipart).
     */
    public function updateWaybillInfo(array $payload): ApiResponse
    {
        return $this->multipartRequest('/shopping/order/updateWaybillInfo', $payload);
    }

    /**
     * Query product price by PID.
     */
    public function getPriceByPid(string $pid): ApiResponse
    {
        return $this->request('GET', '/v1/product/price/queryByPid', ['pid' => $pid]);
    }

    /**
     * Query product price by SKU.
     */
    public function getPriceBySku(string $sku): ApiResponse
    {
        return $this->request('GET', '/v1/product/price/queryBySku', ['sku' => $sku]);
    }

    /**
     * Query product price by VID.
     */
    public function getPriceByVid(string $vid): ApiResponse
    {
        return $this->request('GET', '/v1/product/price/queryByVid', ['vid' => $vid]);
    }

    /**
     * Search product variants.
     */
    public function searchVariants(array $payload): ApiResponse
    {
        return $this->request('POST', '/v1/product/variant/search', $payload);
    }

    private function sign(int $timestamp, string $body): string
    {
        $data = $timestamp . $body;
        // Use apiKey as secret if apiSecret is empty (CJ API doesn't use separate secret)
        $secret = $this->apiSecret ?: $this->apiKey;
        return Str::lower(hash_hmac('sha256', $data, $secret));
    }

    private function assertConfigured(): void
    {
        if ($this->baseUrl === '' || $this->appId === '' || $this->apiKey === '') {
            throw new \RuntimeException('CJdropshipping configuration is missing (base_url/app_id/api_key).');
        }
    }

    private function http(): PendingRequest
    {
        return Http::timeout($this->timeoutSeconds)
            ->retry(2, 200)
            ->acceptJson();
    }

    private function multipartRequest(string $path, array $payload): ApiResponse
    {
        $bodyForSign = $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $timestamp = (int) (microtime(true) * 1000);
        $signature = $this->sign($timestamp, $bodyForSign);

        $headers = [
            'CJ-APIKEY' => $this->apiKey,
            'CJ-APPID' => $this->appId,
            'CJ-TIMESTAMP' => (string) $timestamp,
            'CJ-SIGN' => $signature,
        ];

        if ($this->platformToken) {
            $headers['platformToken'] = $this->platformToken;
        }

        $request = $this->http()->withHeaders($headers);

        foreach ($payload as $key => $value) {
            if ($value instanceof \SplFileInfo) {
                $request = $request->attach($key, file_get_contents($value->getPathname()), $value->getFilename());
            } elseif (is_string($value) && is_file($value)) {
                $request = $request->attach($key, file_get_contents($value), basename($value));
            } else {
                $request = $request->attach($key, (string) $value);
            }
        }

        try {
            $response = $request->post($this->baseUrl . '/' . ltrim($path, '/'));

            return $this->buildResponse($response->body(), $response->status());
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $res = $e->response;
            $raw = $res ? $res->body() : null;
            $status = $res ? $res->status() : 0;

            $decoded = null;
            try {
                $decoded = json_decode((string) $raw, true);
            } catch (\Throwable $_) {
                // ignore
            }

            $requestId = is_array($decoded) && array_key_exists('requestId', $decoded) ? (string) $decoded['requestId'] : null;
            $message = is_array($decoded) && array_key_exists('message', $decoded) ? (string) $decoded['message'] : $e->getMessage();
            $codeString = is_array($decoded) && array_key_exists('code', $decoded) ? (string) $decoded['code'] : null;

            $apiEx = new ApiException($message ?: 'HTTP request failed', $status, $codeString, $decoded ?? $raw, $requestId);

            if ($apiEx->status >= 500 || $apiEx->status === 429) {
                \App\Infrastructure\Fulfillment\Clients\CJ\CjAlertService::alert('CJ API error', [
                    'status' => $apiEx->status,
                    'code' => $apiEx->codeString,
                    'requestId' => $apiEx->requestId,
                    'body' => $apiEx->body,
                ]);
            }

            throw $apiEx;
        }
    }

    private function buildResponse(string $rawBody, int $status): ApiResponse
    {
        $decoded = json_decode($rawBody, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && array_key_exists('result', $decoded) && array_key_exists('code', $decoded)) {
            $ok = (bool) $decoded['result'] && ((int) $decoded['code'] === 200);
            $message = $decoded['message'] ?? null;
            $data = $decoded['data'] ?? null;
            $requestId = is_array($decoded) && array_key_exists('requestId', $decoded) ? (string) $decoded['requestId'] : null;

            if (! $ok) {
                // Log request id to help triage
                Log::warning('CJ API returned error', ['status' => $status, 'code' => $decoded['code'] ?? null, 'requestId' => $requestId]);

                throw new ApiException($message ?: 'API error', $status, (string) ($decoded['code'] ?? ''), $decoded, $requestId);
            }

            return ApiResponse::success($data, $decoded, $message, $status);
        }

        if ($status < 200 || $status >= 300) {
            throw new ApiException('API error', $status, null, $decoded ?? $rawBody);
        }

        return ApiResponse::success($decoded ?? $rawBody, $decoded ?? $rawBody, null, $status);
    }
}
