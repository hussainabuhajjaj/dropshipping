<?php

declare(strict_types=1);

namespace App\Services\Api;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ApiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly array $defaultHeaders = [],
        private readonly int $timeout = 10,
        private readonly int $retryTimes = 3,
        private readonly int $retryDelayMs = 500,
    ) {
    }

    public function withToken(string $token, string $header = 'Authorization'): self
    {
        $headers = $this->defaultHeaders;
        $headers[$header] = str_starts_with(strtolower($header), 'authorization') ? "Bearer {$token}" : $token;

        return new self($this->baseUrl, $headers, $this->timeout, $this->retryTimes, $this->retryDelayMs);
    }

    public function get(string $path, array $query = []): ApiResponse
    {
        return $this->send('get', $path, ['query' => $query]);
    }

    public function post(string $path, array $payload = []): ApiResponse
    {
        return $this->send('post', $path, ['json' => $payload]);
    }

    public function patch(string $path, array $payload = []): ApiResponse
    {
        return $this->send('patch', $path, ['json' => $payload]);
    }

    public function postForm(string $path, array $payload = []): ApiResponse
    {
        return $this->send('post', $path, ['form_params' => $payload]);
    }

    public function getWithHeaders(string $path, array $headers = [], array $query = []): ApiResponse
    {
        $client = new self($this->baseUrl, array_merge($this->defaultHeaders, $headers), $this->timeout);
        return $client->get($path, $query);
    }

    private function send(string $method, string $path, array $options): ApiResponse
    {
        $url = $this->fullUrl($path);

        $request = Http::timeout($this->timeout)
            ->baseUrl($this->baseUrl)
            ->retry($this->retryTimes, $this->retryDelayMs, function ($exception, $request) {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                $response = $exception instanceof RequestException ? $exception->response : null;
                $status = $response?->status();
                if ($status === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? 0);
                    if ($retryAfter > 0) {
                        usleep($retryAfter * 1_000_000);
                    }
                    return true;
                }
                return $status === null; // transient network errors
            })
            ->withHeaders($this->defaultHeaders);

        if (isset($options['form_params'])) {
            $request = $request->asForm();
            $payload = $options['form_params'];
            $response = $request->{$method}($url, $payload);
        } elseif (isset($options['json'])) {
            $response = $request->acceptJson()->{$method}($url, $options['json']);
        } else {
            $response = $request->acceptJson()->{$method}($url, $options['query'] ?? []);
        }

        return $this->buildResponse($response);
    }

    private function fullUrl(string $path): string
    {
        return str_starts_with($path, 'http') ? $path : ltrim($path, '/');
    }

    private function buildResponse(Response $response): ApiResponse
    {
        $raw = $response->json() ?? $response->body();
        $status = $response->status();

        // Generic CJ-style schema: { code, result, message, data }
        if (is_array($raw) && array_key_exists('result', $raw) && array_key_exists('code', $raw)) {
            $ok = (bool) $raw['result'] && ((int) $raw['code'] === 200);
            $message = $raw['message'] ?? null;
            $data = $raw['data'] ?? null;
            if (! $ok) {
                throw new ApiException($message ?: 'API error', $status, (string) ($raw['code'] ?? ''), $raw);
            }
            return ApiResponse::success($data, $raw, $message, $status);
        }

        if (! $response->successful()) {
            throw new ApiException('API error', $status, null, $raw);
        }

        return ApiResponse::success($raw, $raw, null, $status);
    }
}
