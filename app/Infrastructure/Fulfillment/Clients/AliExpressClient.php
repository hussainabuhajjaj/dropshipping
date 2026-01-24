<?php

declare(strict_types=1);

namespace App\Infrastructure\Fulfillment\Clients;

use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Fulfillment\DTOs\FulfillmentRequestData;
use App\Models\AliExpressToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AliExpressClient
{
    public function __construct(
        private readonly FulfillmentProvider $provider
    ) {}

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

        $appKey    = config('ali_express.client_id');
        $appSecret = config('ali_express.client_secret');
        $timestamp = (string) (now()->timestamp * 1000);

        $params = [
            'app_key'       => $appKey,
            'timestamp'     => $timestamp,
            'sign_method'   => 'hmac-sha256',
            'refresh_token' => $token->refresh_token,
        ];

        $params['sign'] = $this->sign($params, $appSecret);

        $url = 'https://api-sg.aliexpress.com/rest/auth/token/refresh';

        $response = Http::asForm()
            ->timeout(30)
            ->post($url, $params);

        $data = $response->json();

        if (!is_array($data) || ($data['code'] ?? null) !== '0') {
            Log::error('AliExpress token refresh failed', $data ?? []);
            throw new FulfillmentException('AliExpress token refresh failed.');
        }

        $token->update([
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
            'expires_at'    => isset($data['expires_in'])
                ? now()->addSeconds((int) $data['expires_in'])
                : null,
            'raw'           => json_encode($data),
        ]);
    }

    // =========================
    // DS API CALLS
    // =========================

    public function searchProducts(array $params): array
    {
        return $this->callDsApi('aliexpress.ds.product.search', $params);
    }

    public function getProduct(array $params): array
    {
        return $this->callDsApi('aliexpress.ds.product.get', $params);
    }

    public function getCategories(): array
    {
        return $this->callDsApi('aliexpress.ds.category.get', []);
    }

    protected function callDsApi(string $method, array $extra): array
    {
        $appKey    = config('ali_express.client_id');
        $appSecret = config('ali_express.client_secret');

        $params = [
            'method'      => $method,
            'app_key'     => $appKey,
            'timestamp'   => (string) (now()->timestamp * 1000),
            'sign_method' => 'hmac-sha256',
            'access_token'=> $this->getAccessToken(),
            ...$extra,
        ];

        $params['sign'] = $this->sign($params, $appSecret);

        $response = Http::asForm()
            ->timeout(30)
            ->post(config('ali_express.api_base'), $params);

        return $response->json() ?? [];
    }

    // =========================
    // SIGNATURE
    // =========================

    private function sign(array $params, string $secret): string
    {
        unset($params['sign']);

        ksort($params);

        $string = '';
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) continue;
            $string .= $k . $v;
        }

        return strtoupper(hash_hmac('sha256', $string, $secret));
    }
}
