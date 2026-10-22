<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Services\CJCircuitBreakerService;

class CJApiClient extends ApiClient
{
    public function __construct(
        string $baseUrl,
        array $defaultHeaders = [],
        int $timeout = 10,
        int $retryTimes = 3,
        int $retryDelayMs = 500,
    ) {
        parent::__construct($baseUrl, $defaultHeaders, $timeout, $retryTimes, $retryDelayMs);
    }

    protected function send(string $method, string $path, array $options): ApiResponse
    {
        $circuitBreaker = app(CJCircuitBreakerService::class);

        return $circuitBreaker->executeApiCall(fn () => parent::send($method, $path, $options));
    }
}