<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class AliExpressCircuitBreakerService
{
    private const RATE_LIMIT = 1;
    private const BURST_CAPACITY = 1;
    private const WINDOW_SIZE = 1;
    private const MAX_DELAY = 300;
    private const CIRCUIT_BREAKER_THRESHOLD = 5;
    private const CIRCUIT_BREAKER_TIMEOUT = 300;

    private string $redisPrefix = 'aliexpress:api:';

    public function executeApiCall(callable $callback): mixed
    {
        $this->waitForRateLimit();
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

    private function waitForRateLimit(): void
    {
        $key = $this->redisPrefix . 'rate_limit';
        $now = now()->timestamp;
        $window = floor($now / self::WINDOW_SIZE) * self::WINDOW_SIZE;

        $script = "
            local key = KEYS[1]
            local window = ARGV[1]
            local now = tonumber(ARGV[2])
            local rate_limit = tonumber(ARGV[3])
            local burst_capacity = tonumber(ARGV[4])

            redis.call('ZREMRANGEBYSCORE', key, 0, window - 1)
            local current = redis.call('ZCARD', key)

            if current >= rate_limit then
                local oldest = redis.call('ZRANGE', key, 0, 0, 'WITHSCORES')
                if #oldest > 0 then
                    return tonumber(oldest[2]) + 1 - now
                end
                return 1
            end

            redis.call('ZADD', key, now, now)
            redis.call('EXPIRE', key, 10)

            return 0
        ";

        $delay = Redis::eval($script, 1, $key, $window, $now, self::RATE_LIMIT, self::BURST_CAPACITY);

        if ($delay > 0) {
            $actualDelay = min($delay, self::MAX_DELAY);
            Log::warning('AliExpress API rate limit active, waiting', [
                'delay' => $actualDelay,
                'current_requests' => $this->getCurrentRequestCount(),
            ]);

            usleep($actualDelay * 1_000_000);
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

        $isOpen = $currentState === 'open';
        if ($isOpen && (now()->timestamp - $openedAt) > self::CIRCUIT_BREAKER_TIMEOUT) {
            Redis::hMSet($key, [
                'state' => 'half_open',
                'failure_count' => 0,
            ]);
            Log::info('AliExpress API circuit breaker transitioning to half-open');
            return;
        }

        if ($isOpen) {
            $timeToRecovery = self::CIRCUIT_BREAKER_TIMEOUT - (now()->timestamp - $openedAt);
            throw new \RuntimeException("AliExpress API circuit breaker is open. Recovery in {$timeToRecovery} seconds");
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

        $this->incrementMetric('success_count');
    }

    private function recordFailure(\Throwable $e): void
    {
        $key = $this->redisPrefix . 'circuit_breaker';
        $state = Redis::hGetAll($key);

        $failureCount = (int) ($state['failure_count'] ?? 0) + 1;
        $errorCode = (string) max(0, (int) ($e->getCode() ?: 0));
        $currentState = $state['state'] ?? 'closed';

        Redis::hMSet($key, [
            'failure_count' => $failureCount,
            'last_failure' => now()->timestamp,
            'last_failure_type' => $errorCode,
        ]);

        if ($currentState === 'half_open' || $failureCount >= self::CIRCUIT_BREAKER_THRESHOLD) {
            Redis::hMSet($key, [
                'state' => 'open',
                'opened_at' => now()->timestamp,
            ]);

            Log::warning('AliExpress API circuit breaker opened', [
                'failure_count' => $failureCount,
                'last_error' => $e->getMessage(),
                'state' => $currentState,
            ]);
        }

        $this->incrementMetric('failure_count');
        $this->incrementMetric('failure_' . $errorCode);
    }

    private function getCurrentRequestCount(): int
    {
        $key = $this->redisPrefix . 'rate_limit';
        $window = floor(now()->timestamp / self::WINDOW_SIZE) * self::WINDOW_SIZE;

        Redis::zremrangebyscore($key, 0, $window - 1);
        return Redis::zcard($key);
    }

    private function incrementMetric(string $metric): void
    {
        $key = $this->redisPrefix . 'metrics:' . $metric;
        Redis::incr($key);
        Redis::expire($key, 86400);
    }
}
