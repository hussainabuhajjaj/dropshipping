<?php

declare(strict_types=1);

namespace App\Infrastructure\Fulfillment\Clients;

use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Fulfillment\DTOs\FulfillmentRequestData;
use App\Domain\Orders\Models\OrderItem;
use App\Models\LocalWareHouse;
use App\Models\AliExpressToken;
use App\Services\AliExpressCircuitBreakerService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AliExpressClient
{
    public function __construct(
        private readonly FulfillmentProvider $provider
    )
    {
    }

    // =========================
    // TOKEN MANAGEMENT
    // =========================

    protected function getAccessToken(): string
    {
        $token = AliExpressToken::latest()->first();

        if (!$token) {
            throw new FulfillmentException('AliExpress not authenticated.');
        }

        if ($token->expires_at && now()->gte($token->expires_at)) {
            $this->refreshToken($token);
            $token->refresh();
        }

        return $token->access_token;
    }

    protected function refreshToken(AliExpressToken $token): void
    {
        if (!$token->refresh_token) {
            throw new FulfillmentException('Missing refresh token.');
        }

        $appKey = config('ali_express.client_id');
        $appSecret = config('ali_express.client_secret');
        $timestamp = (string)(now()->timestamp * 1000);

        $apiPath = "/auth/token/refresh";
        $params = [
            'app_key' => $appKey,
            'timestamp' => $timestamp,
            'sign_method' => 'hmac-sha256',
            'refresh_token' => $token->refresh_token,
        ];

        $params['sign'] = $this->sign($params, $appSecret, $apiPath);

        $url = 'https://api-sg.aliexpress.com/rest' . $apiPath;

        $response = Http::asForm()
            ->timeout(10)
            ->post($url, $params);
        $data = $response->json();

        if (!is_array($data) || ($data['code'] ?? null) !== '0') {
            Log::error('AliExpress token refresh failed', $data ?? []);
            throw new FulfillmentException('AliExpress token refresh failed.');
        }

        $token->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
            'expires_at' => isset($data['expires_in'])
                ? now()->addSeconds((int)$data['expires_in'])
                : null,
            'raw' => json_encode($data),
        ]);
    }

    // =========================
    // DS API CALLS
    // =========================

    public function searchProducts(array $params): array
    {
        return $this->callDsApi('aliexpress.ds.text.search', $params);
    }

    public function getProduct(array $params): array
    {
        return $this->callDsApi('aliexpress.ds.product.get', $params);
    }

    public function getCategories(): array
    {
        return $this->callDsApi('aliexpress.ds.category.get', []);
    }

    public function getCategoryById(string $categoryId): array
    {
        return $this->callDsApi('aliexpress.ds.category.get', [
            'categoryId' => $categoryId,
        ]);
    }

    public function createOrder(FulfillmentRequestData $request): array
    {
        $orderItems = $this->resolveOrderItems($request);
        if ($orderItems === []) {
            throw new FulfillmentException('AliExpress dispatch requires at least one order item.');
        }

        $warehouse = $this->resolveWarehouseForOrderItems($orderItems);
        if (! $warehouse) {
            throw new FulfillmentException('AliExpress dispatch requires a configured local warehouse address.');
        }

        $productItems = [];
        foreach ($orderItems as $orderItem) {
            $variant = $orderItem->productVariant;
            $supplierProduct = $orderItem->supplierProduct;
            $variantMetadata = is_array($variant?->metadata ?? null) ? $variant->metadata : [];
            $productAttributes = is_array($variant?->product?->attributes ?? null) ? $variant->product->attributes : [];

            $externalProductId = $supplierProduct?->external_product_id
                ?? ($productAttributes['ali_item_id'] ?? null);
            $skuAttr = $variantMetadata['ali_sku_attr'] ?? null;

            if (! is_numeric($externalProductId)) {
                throw new FulfillmentException("Missing AliExpress external product id for order item {$orderItem->id}.");
            }

            $productItems[] = array_filter([
                'product_count' => max(1, (int) $orderItem->quantity),
                'product_id' => (int) $externalProductId,
                'sku_attr' => is_string($skuAttr) && trim($skuAttr) !== '' ? trim($skuAttr) : null,
                'order_memo' => $request->options['order_memo'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');
        }

        $response = $this->callDsApi('aliexpress.ds.order.create', array_filter([
            'param_place_order_request4_open_api_d_t_o' => [
                'out_order_id' => (string) ($request->order_id ?? ('ae-' . Str::uuid()->toString())),
                'logistics_address' => $this->buildWarehouseAddressPayload($warehouse),
                'product_items' => $productItems,
            ],
            'payment' => [
                'pay_currency' => strtoupper((string) ($request->options['currency'] ?? 'USD')),
            ],
            'trade_extra_param' => [
                'business_model' => (string) ($request->options['business_model'] ?? 'retail'),
            ],
        ], fn ($value) => $value !== null && $value !== []));

        if (($response['code'] ?? null) !== '0') {
            $error = $response['msg'] ?? $response['rsp_msg'] ?? 'AliExpress order create failed.';
            throw new FulfillmentException((string) $error);
        }

        return $response;
    }

    public function freightQuery(array $queryDeliveryReq): array
    {
        $response = $this->callDsApi('aliexpress.ds.freight.query', [
            'queryDeliveryReq' => $queryDeliveryReq,
        ]);

        if (($response['code'] ?? null) !== '0' && ($response['success'] ?? null) !== true) {
            $error = $response['msg'] ?? $response['rsp_msg'] ?? 'AliExpress freight query failed.';
            throw new FulfillmentException((string) $error);
        }

        return $response;
    }

    protected function callDsApi(string $method, array $extra): array
    {
        $appKey = config('ali_express.client_id');
        $appSecret = config('ali_express.client_secret');

        $normalizedExtra = [];
        foreach ($extra as $key => $value) {
            $normalizedExtra[$key] = $this->normalizeDsParamValue($value);
        }

        $params = [
            'method' => $method,
            'app_key' => $appKey,
            'timestamp' => (string)(now()->timestamp * 1000),
            'sign_method' => 'sha256',
            'access_token' => $this->getAccessToken(),
            ...$normalizedExtra,
        ];

        $params['sign'] = $this->sign($params, $appSecret, $method);


        $execute = function () use ($params) {
            $url = config('ali_express.base_url') . "/rest";

            $response = Http::asForm()
                ->timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8',
                ])
                ->post($url, $params);

            return $response->json() ?? [];
        };

        return app(AliExpressCircuitBreakerService::class)->executeApiCall($execute);
    }

    private function resolveOrderItems(FulfillmentRequestData $request): array
    {
        if ($request->orderItem instanceof OrderItem) {
            return [$request->orderItem->loadMissing(['supplierProduct', 'productVariant.product.localWarehouse'])];
        }

        $ids = collect($request->order_items ?? [])
            ->map(fn ($item) => is_array($item) ? ($item['id'] ?? null) : null)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return OrderItem::query()
            ->with(['supplierProduct', 'productVariant.product.localWarehouse'])
            ->whereIn('id', $ids->all())
            ->get()
            ->all();
    }

    private function resolveWarehouseForOrderItems(array $orderItems): ?LocalWareHouse
    {
        foreach ($orderItems as $orderItem) {
            $warehouse = $orderItem->productVariant?->product?->localWarehouse;
            if ($warehouse instanceof LocalWareHouse) {
                return $warehouse;
            }
        }

        return LocalWareHouse::query()
            ->where('is_default', true)
            ->orderBy('id')
            ->first();
    }

    private function buildWarehouseAddressPayload(LocalWareHouse $warehouse): array
    {
        return array_filter([
            'address' => trim((string) $warehouse->line1),
            'address2' => trim((string) ($warehouse->line2 ?? '')) ?: null,
            'city' => trim((string) $warehouse->city),
            'contact_person' => trim((string) $warehouse->name) ?: null,
            'country' => strtoupper(trim((string) $warehouse->country)),
            'full_name' => trim((string) $warehouse->name) ?: null,
            'locale' => str_replace('-', '_', app()->getLocale()),
            'mobile_no' => trim((string) ($warehouse->phone ?? '')) ?: null,
            'phone_country' => strtoupper(trim((string) $warehouse->country)),
            'province' => trim((string) ($warehouse->state ?? '')) ?: trim((string) $warehouse->city),
            'zip' => trim((string) ($warehouse->postal_code ?? '')) ?: null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function normalizeDsParamValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return $value;
    }

    // =========================
    // SIGNATURE
    // =========================

    /**
     * Generate AliExpress API signature (HMAC-SHA256)
     *
     * @param array $params All request params (system + business), EXCEPT sign
     * @param string $appSecret App Secret
     * @param string $apiName API name or api_path
     * @param bool $isSystem true = System Interface, false = Business Interface
     *
     * @return string Uppercase HEX signature
     */
    public function sign(
        array  $params,
        string $appSecret,
        string $apiName,
        bool   $isSystem = true
    ): string
    {
        // 1. If Business Interface → api_path participates in sorting
        if (!$isSystem) {
            // api_path is usually passed as "method"
            $params['method'] = $apiName;
        }

        // Remove sign if exists
        unset($params['sign']);

        // 2. Sort parameters by ASCII order of key
        ksort($params);

        // 3. Concatenate parameters
        $stringToSign = '';

        // System Interface → prepend API name
        if ($isSystem) {
            $stringToSign .= $apiName;
        }

        foreach ($params as $key => $value) {
            if ($key === '' || $value === '' || $value === null) {
                continue;
            }
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $stringToSign .= $key . $value;
        }

        // 4. HMAC-SHA256
        $hash = hash_hmac(
            'sha256',
            $stringToSign,
            $appSecret,
            true // raw binary
        );

        // 5. Convert to UPPERCASE HEX
        return strtoupper(bin2hex($hash));
    }
}
