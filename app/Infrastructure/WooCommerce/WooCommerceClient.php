<?php

declare(strict_types=1);

namespace App\Infrastructure\WooCommerce;

use App\Domain\WooCommerce\Contracts\WooCommerceClientContract;
use App\Domain\WooCommerce\DTOs\WooCommerceCustomerData;
use App\Domain\WooCommerce\DTOs\WooCommerceOrderData;
use App\Domain\WooCommerce\DTOs\WooCommerceProductData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WooCommerceClient implements WooCommerceClientContract
{
    private string $baseUrl;

    private string $consumerKey;

    private string $consumerSecret;

    private int $timeout;

    private int $retryTimes;

    private int $retryDelayMs;

    private bool $verifySsl;

    public function __construct()
    {
        $config = config('woocommerce');

        $this->baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $this->consumerKey = (string) ($config['consumer_key'] ?? '');
        $this->consumerSecret = (string) ($config['consumer_secret'] ?? '');
        $this->timeout = (int) ($config['timeout'] ?? 30);
        $this->retryTimes = (int) ($config['retry_times'] ?? 3);
        $this->retryDelayMs = (int) ($config['retry_delay_ms'] ?? 500);
        $this->verifySsl = (bool) ($config['verify_ssl'] ?? true);

        if ($this->baseUrl === '' || $this->consumerKey === '' || $this->consumerSecret === '') {
            throw new RuntimeException('WooCommerce is not configured. Set WC_BASE_URL, WC_CONSUMER_KEY, and WC_CONSUMER_SECRET.');
        }
    }

    public function getProduct(int $productId): WooCommerceProductData
    {
        $response = $this->get("/products/{$productId}");

        return $this->mapProductData($response);
    }

    public function getProducts(array $filters = []): array
    {
        $response = $this->get('/products', $filters);

        return array_map(fn (array $item) => $this->mapProductData($item), $this->extractList($response));
    }

    public function getProductsPage(int $page = 1, int $perPage = 20, ?string $search = null): array
    {
        $url = "{$this->baseUrl}/wp-json/wc/v3/products";

        $params = ['page' => $page, 'per_page' => $perPage];
        if ($search !== null && $search !== '') {
            $params['search'] = $search;
        }

        $this->logRequest('GET', $url, $params);

        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->timeout($this->timeout)
            ->withOptions(['verify' => $this->verifySsl])
            ->retry($this->retryTimes, $this->retryDelayMs, function (ConnectionException|RequestException $exception) {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                $status = $exception->response?->status();

                if ($status === 429) {
                    $retryAfter = (int) ($exception->response->header('Retry-After') ?? 5);
                    sleep(min($retryAfter, 30));
                    return true;
                }

                return in_array($status, [500, 502, 503, 504], true);
            }, throw: false)
            ->get($url, $params);

        $status = $response->status();
        $body = $response->json() ?? [];

        $this->logResponse('GET', $url, $status, $params);

        if ($response->failed()) {
            $errorMessage = $this->extractErrorMessage($body, $status);
            throw new WooCommerceApiException(
                message: $errorMessage,
                statusCode: $status,
                responseBody: $body,
            );
        }

        $products = array_map(fn (array $item) => $this->mapProductData($item), $this->extractList($body));

        return [
            'products' => $products,
            'total' => (int) ($response->header('X-WP-Total', 0)),
            'totalPages' => (int) ($response->header('X-WP-TotalPages', 0)),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function getProductBySku(string $sku): ?WooCommerceProductData
    {
        $response = $this->get('/products', ['sku' => $sku]);
        $items = $this->extractList($response);

        if (empty($items)) {
            return null;
        }

        return $this->mapProductData($items[0]);
    }

    public function createProduct(array $data): array
    {
        return $this->post('/products', $data);
    }

    public function updateProduct(int $productId, array $data): array
    {
        return $this->put("/products/{$productId}", $data);
    }

    public function deleteProduct(int $productId): bool
    {
        $response = $this->delete("/products/{$productId}", ['force' => true]);

        return ($response['deleted'] ?? false) === true;
    }

    public function getVariation(int $productId, int $variationId): WooCommerceProductData
    {
        $response = $this->get("/products/{$productId}/variations/{$variationId}");

        return $this->mapVariationData($response, $productId);
    }

    public function getVariations(int $productId): array
    {
        $response = $this->get("/products/{$productId}/variations", ['per_page' => 100]);

        return array_map(
            fn (array $item) => $this->mapVariationData($item, $productId),
            $this->extractList($response),
        );
    }

    public function createVariation(int $productId, array $data): array
    {
        return $this->post("/products/{$productId}/variations", $data);
    }

    public function updateVariation(int $productId, int $variationId, array $data): array
    {
        return $this->put("/products/{$productId}/variations/{$variationId}", $data);
    }

    public function getCategories(array $filters = []): array
    {
        $response = $this->get('/products/categories', $filters);

        return $this->extractList($response);
    }

    public function createCategory(array $data): array
    {
        return $this->post('/products/categories', $data);
    }

    public function updateCategory(int $categoryId, array $data): array
    {
        return $this->put("/products/categories/{$categoryId}", $data);
    }

    public function getCustomer(int $customerId): WooCommerceCustomerData
    {
        $response = $this->get("/customers/{$customerId}");

        return $this->mapCustomerData($response);
    }

    public function getCustomers(array $filters = []): array
    {
        $response = $this->get('/customers', $filters);

        return array_map(fn (array $item) => $this->mapCustomerData($item), $this->extractList($response));
    }

    public function getCustomerByEmail(string $email): ?WooCommerceCustomerData
    {
        $response = $this->get('/customers', ['email' => $email]);
        $items = $this->extractList($response);

        if (empty($items)) {
            return null;
        }

        return $this->mapCustomerData($items[0]);
    }

    public function createCustomer(array $data): array
    {
        return $this->post('/customers', $data);
    }

    public function updateCustomer(int $customerId, array $data): array
    {
        return $this->put("/customers/{$customerId}", $data);
    }

    public function getOrder(int $orderId): WooCommerceOrderData
    {
        $response = $this->get("/orders/{$orderId}");

        return $this->mapOrderData($response);
    }

    public function getOrders(array $filters = []): array
    {
        $response = $this->get('/orders', $filters);

        return array_map(fn (array $item) => $this->mapOrderData($item), $this->extractList($response));
    }

    public function createOrder(array $data): array
    {
        return $this->post('/orders', $data);
    }

    public function updateOrder(int $orderId, array $data): array
    {
        return $this->put("/orders/{$orderId}", $data);
    }

    public function getOrderNotes(int $orderId): array
    {
        $response = $this->get("/orders/{$orderId}/notes");

        return $this->extractList($response);
    }

    public function addOrderNote(int $orderId, string $note, bool $customerNote = false): array
    {
        return $this->post("/orders/{$orderId}/notes", [
            'note' => $note,
            'customer_note' => $customerNote,
        ]);
    }

    public function updateStock(int $productId, int $quantity, ?int $variationId = null): array
    {
        $data = [
            'manage_stock' => true,
            'stock_quantity' => $quantity,
        ];

        if ($variationId !== null) {
            return $this->put("/products/{$productId}/variations/{$variationId}", $data);
        }

        return $this->put("/products/{$productId}", $data);
    }

    public function getShipmentTrackings(int $orderId): array
    {
        $response = $this->get("/orders/{$orderId}/shipment-trackings", []);

        return $this->extractList($response);
    }

    public function addShipmentTracking(int $orderId, array $data): array
    {
        return $this->post("/orders/{$orderId}/shipment-trackings", $data);
    }

    public function getWebhooks(array $filters = []): array
    {
        $response = $this->get('/webhooks', $filters);

        return $this->extractList($response);
    }

    public function createWebhook(array $data): array
    {
        return $this->post('/webhooks', $data);
    }

    public function deleteWebhook(int $webhookId): bool
    {
        try {
            $this->delete("/webhooks/{$webhookId}", ['force' => 'true']);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->get('/products', ['per_page' => 1]);
            return is_array($response);
        } catch (\Throwable) {
            return false;
        }
    }

    private function get(string $path, array $query = []): array
    {
        return $this->send('get', $path, $query);
    }

    private function post(string $path, array $data = []): array
    {
        return $this->send('post', $path, $data);
    }

    private function put(string $path, array $data = []): array
    {
        return $this->send('put', $path, $data);
    }

    private function delete(string $path, array $data = []): array
    {
        return $this->send('delete', $path, $data);
    }

    private function send(string $method, string $path, array $payload = []): array
    {
        $url = $this->baseUrl . '/wp-json/wc/v3/' . ltrim($path, '/');

        $this->logRequest($method, $url, $payload);

        $request = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->timeout($this->timeout)
            ->withOptions(['verify' => $this->verifySsl])
            ->retry($this->retryTimes, $this->retryDelayMs, function (ConnectionException|RequestException $exception) {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                $status = $exception->response?->status();

                if ($status === 429) {
                    $retryAfter = (int) ($exception->response->header('Retry-After') ?? 5);
                    sleep(min($retryAfter, 30));

                    return true;
                }

                return in_array($status, [500, 502, 503, 504], true);
            }, throw: false);

        $response = match ($method) {
            'get' => $request->get($url, $payload),
            'post' => $request->acceptJson()->post($url, $payload),
            'put' => $request->acceptJson()->put($url, $payload),
            'delete' => $request->acceptJson()->delete($url, $payload),
            default => throw new RuntimeException("Unsupported HTTP method: {$method}"),
        };

        $body = $response->json() ?? [];
        $status = $response->status();

        $this->logResponse($method, $url, $status, $body);

        if ($response->successful()) {
            return $body;
        }

        $errorMessage = $this->extractErrorMessage($body, $status);

        throw new WooCommerceApiException(
            message: $errorMessage,
            statusCode: $status,
            responseBody: $body,
        );
    }

    private function extractList(array $response): array
    {
        // WooCommerce returns lists directly as arrays, or with _embedded
        if (array_is_list($response)) {
            return $response;
        }

        return $response['data'] ?? [];
    }

    private function extractErrorMessage(array $body, int $status): string
    {
        $message = $body['message']
            ?? $body['error']
            ?? $body['description']
            ?? 'WooCommerce API error';

        $code = $body['code'] ?? '';

        return "WooCommerce API error (HTTP {$status}): [{$code}] {$message}";
    }

    private function mapProductData(array $data): WooCommerceProductData
    {
        return new WooCommerceProductData(
            woocommerceId: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            slug: (string) ($data['slug'] ?? ''),
            sku: (string) ($data['sku'] ?? ''),
            type: (string) ($data['type'] ?? 'simple'),
            status: (string) ($data['status'] ?? 'draft'),
            description: $data['description'] ?? null,
            shortDescription: $data['short_description'] ?? null,
            price: ($data['price'] ?? '') !== '' ? (float) $data['price'] : null,
            regularPrice: ($data['regular_price'] ?? '') !== '' ? (float) $data['regular_price'] : null,
            salePrice: ($data['sale_price'] ?? '') !== '' ? (float) $data['sale_price'] : null,
            currency: $this->resolveProductCurrency($data),
            weight: ($data['weight'] ?? '') !== '' ? (float) $data['weight'] : null,
            length: ($data['dimensions']['length'] ?? '') !== '' ? (float) $data['dimensions']['length'] : null,
            width: ($data['dimensions']['width'] ?? '') !== '' ? (float) $data['dimensions']['width'] : null,
            height: ($data['dimensions']['height'] ?? '') !== '' ? (float) $data['dimensions']['height'] : null,
            manageStock: (bool) ($data['manage_stock'] ?? false),
            stockQuantity: isset($data['stock_quantity']) && $data['stock_quantity'] !== null ? (int) $data['stock_quantity'] : null,
            stockStatus: (string) ($data['stock_status'] ?? 'instock'),
            categories: $data['categories'] ?? [],
            images: $data['images'] ?? [],
            attributes: $data['attributes'] ?? [],
            variations: $data['variations'] ?? [],
            metaData: $data['meta_data'] ?? [],
            rawData: $data,
        );
    }

    private function mapVariationData(array $data, int $parentId): WooCommerceProductData
    {
        $base = $this->mapProductData($data);

        return new WooCommerceProductData(
            woocommerceId: $base->woocommerceId,
            woocommerceVariationId: $base->woocommerceId,
            name: $base->name,
            slug: $base->slug,
            sku: $base->sku,
            type: 'variation',
            status: $base->status,
            description: $base->description,
            shortDescription: $base->shortDescription,
            price: $base->price,
            regularPrice: $base->regularPrice,
            salePrice: $base->salePrice,
            currency: $base->currency,
            weight: $base->weight,
            length: $base->length,
            width: $base->width,
            height: $base->height,
            manageStock: $base->manageStock,
            stockQuantity: $base->stockQuantity,
            stockStatus: $base->stockStatus,
            categories: $base->categories,
            images: $base->images,
            attributes: $base->attributes,
            variations: [],
            metaData: $base->metaData,
            rawData: $base->rawData,
        );
    }

    private function resolveProductCurrency(array $data): string
    {
        $raw = $data['currency'] ?? null;

        if (! is_scalar($raw) || trim((string) $raw) === '') {
            $sourceUrl = $this->metaDataValue($data, '_product_upload_source_url')
                ?? $this->metaDataValue($data, '_pu_source_key');

            if (is_string($sourceUrl) && str_contains($sourceUrl, '1688.com')) {
                return 'CNY';
            }

            $raw = config('woocommerce.currency', config('currency.base', 'USD'));
        }

        if (! is_scalar($raw) || trim((string) $raw) === '') {
            $raw = config('currency.base', 'USD');
        }

        return strtoupper(trim((string) $raw));
    }

    private function metaDataValue(array $data, string $key): mixed
    {
        $metaData = $data['meta_data'] ?? [];
        if (! is_array($metaData)) {
            return null;
        }

        foreach ($metaData as $meta) {
            if (is_array($meta) && ($meta['key'] ?? null) === $key) {
                return $meta['value'] ?? null;
            }
        }

        return null;
    }

    private function mapCustomerData(array $data): WooCommerceCustomerData
    {
        return new WooCommerceCustomerData(
            woocommerceId: (int) ($data['id'] ?? 0),
            email: (string) ($data['email'] ?? ''),
            firstName: (string) ($data['first_name'] ?? ''),
            lastName: (string) ($data['last_name'] ?? ''),
            phone: $data['billing']['phone'] ?? $data['phone'] ?? null,
            vat: $data['meta_data']['vat_number'] ?? null,
            country: $data['billing']['country'] ?? null,
            state: $data['billing']['state'] ?? null,
            city: $data['billing']['city'] ?? null,
            postalCode: $data['billing']['postcode'] ?? null,
            addressLine1: $data['billing']['address_1'] ?? null,
            addressLine2: $data['billing']['address_2'] ?? null,
            billing: $data['billing'] ?? [],
            shipping: $data['shipping'] ?? [],
            metaData: $data['meta_data'] ?? [],
            rawData: $data,
        );
    }

    private function mapOrderData(array $data): WooCommerceOrderData
    {
        return new WooCommerceOrderData(
            woocommerceId: (int) ($data['id'] ?? 0),
            number: (string) ($data['number'] ?? ''),
            status: (string) ($data['status'] ?? 'pending'),
            currency: (string) ($data['currency'] ?? 'USD'),
            total: ($data['total'] ?? '') !== '' ? (float) $data['total'] : null,
            subtotal: ($data['subtotal'] ?? '') !== '' ? (float) $data['subtotal'] : null,
            shippingTotal: ($data['shipping_total'] ?? '') !== '' ? (float) $data['shipping_total'] : null,
            taxTotal: ($data['total_tax'] ?? '') !== '' ? (float) $data['total_tax'] : null,
            discountTotal: ($data['discount_total'] ?? '') !== '' ? (float) $data['discount_total'] : null,
            customerId: $data['customer_id'] ?? null,
            customerEmail: (string) ($data['billing']['email'] ?? $data['customer_email'] ?? ''),
            paymentMethod: $data['payment_method'] ?? null,
            paymentMethodTitle: $data['payment_method_title'] ?? null,
            transactionId: $data['transaction_id'] ?? null,
            lineItems: $data['line_items'] ?? [],
            shippingLines: $data['shipping_lines'] ?? [],
            taxLines: $data['tax_lines'] ?? [],
            feeLines: $data['fee_lines'] ?? [],
            couponLines: $data['coupon_lines'] ?? [],
            billing: $data['billing'] ?? [],
            shipping: $data['shipping'] ?? [],
            metaData: $data['meta_data'] ?? [],
            dateCreated: isset($data['date_created']) ? new \DateTimeImmutable($data['date_created']) : null,
            dateModified: isset($data['date_modified']) ? new \DateTimeImmutable($data['date_modified']) : null,
            datePaid: isset($data['date_paid']) ? new \DateTimeImmutable($data['date_paid']) : null,
            dateCompleted: isset($data['date_completed']) ? new \DateTimeImmutable($data['date_completed']) : null,
            rawData: $data,
        );
    }

    private function logRequest(string $method, string $url, array $payload): void
    {
        Log::channel('woocommerce')->debug('WooCommerce API request', [
            'method' => strtoupper($method),
            'url' => $url,
            'has_payload' => $payload !== [],
            'payload_keys' => array_keys($payload),
        ]);
    }

    private function logResponse(string $method, string $url, int $status, array $body): void
    {
        $level = $status >= 400 ? 'warning' : 'debug';

        Log::channel('woocommerce')->log($level, 'WooCommerce API response', [
            'method' => strtoupper($method),
            'url' => $url,
            'status' => $status,
            'response_keys' => is_array($body) ? array_keys($body) : null,
        ]);
    }
}
