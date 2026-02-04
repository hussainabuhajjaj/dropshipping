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
        $config = config('services.korapay', []);
        $secret = (string) ($config['secret_key'] ?? '');

        if ($secret === '') {
            throw new RuntimeException('Korapay secret key is not configured.');
        }

        $this->publicKey = (string) ($config['public_key'] ?? '');
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $this->initializeEndpoint = (string) ($config['initialize_endpoint'] ?? '');
        $this->verifyEndpoint = (string) ($config['verify_endpoint'] ?? '');

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
