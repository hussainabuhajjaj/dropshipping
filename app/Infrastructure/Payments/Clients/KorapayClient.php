<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Clients;

use App\Services\Api\ApiClient;
use App\Services\Api\ApiException;
use App\Services\Api\ApiResponse;
use RuntimeException;

class KorapayClient
{
    private ApiClient $client;
    private string $publicKey;
    private string $baseUrl;
    private string $initializeEndpoint;
    private string $verifyEndpoint;

    public function __construct()
    {
        $servicesConfig = config('services.korapay', []);
        $legacyConfig = config('korapay', []);

        $secret = (string) ($servicesConfig['secret_key'] ?? $legacyConfig['secret_key'] ?? '');

        if ($secret === '') {
            throw new RuntimeException('Korapay secret key is not configured.');
        }

        $this->publicKey = (string) ($servicesConfig['public_key'] ?? $legacyConfig['public_key'] ?? '');
        $this->baseUrl = rtrim((string) ($servicesConfig['base_url'] ?? $legacyConfig['baseUrl'] ?? 'https://api.korapay.com'), '/');
        $this->initializeEndpoint = (string) ($servicesConfig['initialize_endpoint'] ?? '/merchant/api/v1/charges/initialize');
        $this->verifyEndpoint = (string) ($servicesConfig['verify_endpoint'] ?? '/merchant/api/v1/charges/{reference}');

        if ($this->baseUrl === '' || $this->initializeEndpoint === '' || $this->verifyEndpoint === '') {
            throw new RuntimeException('Korapay endpoints are not configured.');
        }

        $this->client = (new ApiClient($this->baseUrl, ['Accept' => 'application/json']))->withToken($secret);
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    public function initialize(array $payload): ApiResponse
    {
        $response = $this->client->post($this->initializeEndpoint, $payload);

        return $this->unwrap($response);
    }

    public function verify(string $reference): ApiResponse
    {
        $path = $this->verifyEndpoint;
        $query = [];

        if (str_contains($this->verifyEndpoint, '{reference}')) {
            $path = str_replace('{reference}', urlencode($reference), $this->verifyEndpoint);
        } else {
            $query = ['reference' => $reference];
        }

        $response = $this->client->get($path, $query);

        return $this->unwrap($response);
    }

    private function unwrap(ApiResponse $response): ApiResponse
    {
        $payload = is_array($response->data) ? $response->data : [];
        $status = (bool) ($payload['status'] ?? false);

        if (! $status) {
            $message = is_array($payload) ? ($payload['message'] ?? 'Korapay API error') : 'Korapay API error';
            throw new ApiException($message, $response->status, null, $payload);
        }

        return ApiResponse::success($payload['data'] ?? null, $payload, $payload['message'] ?? null, $response->status);
    }
}
