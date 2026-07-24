<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CJCircuitBreakerService
{
    private const FAILURE_THRESHOLD = 5;
    private const RECOVERY_TIMEOUT = 60;
    private string $redisPrefix = 'cj:api:';

    public function executeApiCall(callable $callback): mixed
    {
        $this->checkCircuitBreaker();

        try {
            $result = $callback();
            $this->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure($e);
            throw $e;
        }
    }

    private function checkCircuitBreaker(): void
    {
        $key = $this->redisPrefix . 'circuit_breaker';
        $state = Redis::hGetAll($key);

        if (empty($state)) {
            return;
        }

        $currentState = $state['state'] ?? 'closed';
        $openedAt = (int) ($state['opened_at'] ?? 0);

        if ($currentState === 'half_open') {
            return;
        }

        if ($currentState === 'open' && (now()->timestamp - $openedAt) > self::RECOVERY_TIMEOUT) {
            Redis::hMSet($key, [
                'state' => 'half_open',
                'failure_count' => 0,
            ]);
            Log::info('CJ API circuit breaker transitioning to half-open');
            return;
        }

        if ($currentState === 'open') {
            $remaining = self::RECOVERY_TIMEOUT - (now()->timestamp - $openedAt);
            throw new \RuntimeException("CJ API circuit breaker is open. Recovery in {$remaining} seconds");
        }
    }

    private function recordSuccess(): void
    {
        $key = $this->redisPrefix . 'circuit_breaker';

        Redis::hMSet($key, [
            'state' => 'closed',
            'failure_count' => 0,
            'last_success' => now()->timestamp,
        ]);
    }

    private function recordFailure(\Throwable $e): void
    {
        $key = $this->redisPrefix . 'circuit_breaker';
        $state = Redis::hGetAll($key);

        $failureCount = (int) ($state['failure_count'] ?? 0) + 1;
        $currentState = $state['state'] ?? 'closed';

        Redis::hMSet($key, [
            'failure_count' => $failureCount,
            'last_failure' => now()->timestamp,
            'last_failure_type' => $e->getCode() ?: 'exception',
        ]);

        if ($currentState === 'half_open' || $failureCount >= self::FAILURE_THRESHOLD) {
            Redis::hMSet($key, [
                'state' => 'open',
                'opened_at' => now()->timestamp,
            ]);

            Log::warning('CJ API circuit breaker opened', [
                'failure_count' => $failureCount,
                'last_error' => $e->getMessage(),
                'state' => $currentState,
            ]);
        }
    }
}