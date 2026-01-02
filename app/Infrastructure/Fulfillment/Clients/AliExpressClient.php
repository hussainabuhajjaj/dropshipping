<?php

declare(strict_types=1);

namespace App\Infrastructure\Fulfillment\Clients;

use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Fulfillment\DTOs\FulfillmentRequestData;
use App\Models\AliExpressToken;
use Illuminate\Support\Facades\Http;

class AliExpressClient
{
    public function __construct(private readonly FulfillmentProvider $provider)
    {
    }

    /**
        * Dispatch an order to AliExpress.
        * In a real implementation, map credentials/settings to the AliExpress API contract.
        */
    public function createOrder(FulfillmentRequestData $request): array
    {
        $baseUrl = $this->provider->settings['base_url'] ?? 'https://api.aliexpress.com';
        $token = $this->provider->credentials['access_token'] ?? null;

        if (! $token) {
            throw new FulfillmentException('AliExpress credentials are missing.');
        }

        $payload = [
            'order_item_id' => $request->orderItem->id,
            'quantity' => $request->orderItem->quantity,
            'sku' => $request->supplierProduct?->external_sku ?? $request->orderItem->source_sku,
            'shipping_address' => $request->shippingAddress?->toArray(),
            'billing_address' => $request->billingAddress?->toArray(),
            'options' => $request->options,
        ];

        $response = Http::baseUrl($baseUrl)
            ->timeout(20)
            ->withToken($token)
            ->post('/orders', $payload);

        if ($response->failed()) {
            throw new FulfillmentException(
                'AliExpress order creation failed: '.$response->body()
            );
        }

        return $response->json();
    }

    // --- Dropshipping API methods ---
    protected function getAccessToken(): string
    {
        $token = AliExpressToken::getLatestToken();
        
        if (!$token) {
            throw new FulfillmentException('AliExpress access token not found. Please authenticate first.');
        }

        // Auto-refresh if expired but can refresh
        if ($token->isExpired() && $token->canRefresh()) {
            $this->refreshToken($token);
            $token = AliExpressToken::getLatestToken();
        }

        if (!$token || $token->isExpired()) {
            throw new FulfillmentException('AliExpress access token is expired. Please re-authenticate at /admin/aliexpress-import');
        }

        return $token->access_token;
    }

    protected function refreshToken(AliExpressToken $token): void
    {
        try {
            $response = Http::asForm()->post(
                'https://api-sg.aliexpress.com/rest/auth/token/create',
                [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $token->refresh_token,
                    'client_id' => config('ali_express.client_id'),
                    'client_secret' => config('ali_express.client_secret'),
                ]
            );

            $data = $response->json();

            if (!isset($data['access_token'])) {
                throw new \Exception('Token refresh failed: ' . json_encode($data));
            }

            $token->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
                'expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
                'raw' => json_encode($data),
            ]);

            \Illuminate\Support\Facades\Log::info('AliExpress token auto-refreshed');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AliExpress token refresh failed', ['error' => $e->getMessage()]);
            throw new FulfillmentException('Failed to refresh AliExpress token: ' . $e->getMessage());
        }
    }

    public function searchProducts(array $params): array
    {
        $response = Http::asForm()->post(
            config('ali_express.api_base'), [
                'method' => 'aliexpress.ds.product.search',
                'app_key' => config('ali_express.client_id'),
                'access_token' => $this->getAccessToken(),
                'timestamp' => now()->timestamp * 1000,
                'sign_method' => 'hmac',
                // ...other required params and signature
                ...$params,
            ]
        );
        return $response->json();
    }

    public function getProduct(array $params): array
    {
        $response = Http::asForm()->post(
            config('ali_express.api_base'), [
                'method' => 'aliexpress.ds.product.get',
                'app_key' => config('ali_express.client_id'),
                'access_token' => $this->getAccessToken(),
                'timestamp' => now()->timestamp * 1000,
                'sign_method' => 'hmac',
                // ...other required params and signature
                ...$params,
            ]
        );
        return $response->json();
    }

    public function getCategories(): array
    {
        $response = Http::asForm()->post(
            config('ali_express.api_base'), [
                'method' => 'aliexpress.ds.category.get',
                'app_key' => config('ali_express.client_id'),
                'access_token' => $this->getAccessToken(),
                'timestamp' => now()->timestamp * 1000,
                'sign_method' => 'hmac',
                // ...other required params and signature
            ]
        );
        return $response->json();
    }
}
