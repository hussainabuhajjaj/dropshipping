<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CjApiRateLimiterService
{
    private const RATE_LIMIT = 1; // requests per second
    private const BURST_CAPACITY = 1; // CJ is currently enforcing 1 request/second
    private const WINDOW_SIZE = 1; // 1 second windows
    private const MAX_DELAY = 300; // 5 minutes max delay
    private const CIRCUIT_BREAKER_THRESHOLD = 10; // failures before opening circuit
    private const CIRCUIT_BREAKER_TIMEOUT = 300; // 5 minutes to recover

    private CJDropshippingClient $client;
    private string $redisPrefix = 'cj:api:';

    public function __construct(CJDropshippingClient $client)
    {
        $this->client = $client;
    }

    /**
     * Execute API call with rate limiting and circuit breaker protection
     */
    public function executeApiCall(string $method, array $params = []): mixed
    {
        $this->waitForRateLimit();
        $this->checkCircuitBreaker();

        try {
            $result = $this->client->{$method}(...$params);
            $this->recordSuccess();
            return $result;
        } catch (ApiException $e) {
            $this->recordFailure($e);
            throw $e;
        }
    }

    /**
     * Wait if rate limit would be exceeded
     */
    private function waitForRateLimit(): void
    {
        $key = $this->redisPrefix . 'rate_limit';
        $now = now()->timestamp;
        $window = floor($now / self::WINDOW_SIZE) * self::WINDOW_SIZE;

        // Use Redis sliding window counter
        $script = "
            local key = KEYS[1]
            local window = ARGV[1]
            local now = tonumber(ARGV[2])
            local rate_limit = tonumber(ARGV[3])
            local burst_capacity = tonumber(ARGV[4])
            
            -- Remove old entries
            redis.call('ZREMRANGEBYSCORE', key, 0, window - 1)
            
            -- Count current requests
            local current = redis.call('ZCARD', key)
            
            if current >= rate_limit then
                -- Calculate when we can make the next request
                local oldest = redis.call('ZRANGE', key, 0, 0, 'WITHSCORES')
                if #oldest > 0 then
                    return tonumber(oldest[2]) + 1 - now
                end
                return 1
            end
            
            -- Add current request
            redis.call('ZADD', key, now, now)
            redis.call('EXPIRE', key, 10)
            
            return 0
        ";

        $delay = Redis::eval($script, 1, $key, $window, $now, self::RATE_LIMIT, self::BURST_CAPACITY);

        if ($delay > 0) {
            $actualDelay = min($delay, self::MAX_DELAY);
            Log::info('CJ API rate limit active, waiting', [
                'delay' => $actualDelay,
                'current_requests' => $this->getCurrentRequestCount(),
            ]);
            
            // Use non-blocking approach
            usleep($actualDelay * 1_000_000);
        }
    }

    /**
     * Check if circuit breaker is open
     */
    private function checkCircuitBreaker(): void
    {
        $key = $this->redisPrefix . 'circuit_breaker';
        $state = Redis::hgetall($key);

        if (empty($state)) {
            return;
        }

        $isOpen = ($state['state'] ?? 'closed') === 'open';
        $openedAt = (int) ($state['opened_at'] ?? 0);
        $failureCount = (int) ($state['failure_count'] ?? 0);

        if ($isOpen && (now()->timestamp - $openedAt) > self::CIRCUIT_BREAKER_TIMEOUT) {
            // Try to close circuit breaker
            Redis::hmset($key, [
                'state' => 'half_open',
                'failure_count' => 0,
            ]);
            Log::info('CJ API circuit breaker transitioning to half-open');
            return;
        }

        if ($isOpen) {
            $timeToRecovery = self::CIRCUIT_BREAKER_TIMEOUT - (now()->timestamp - $openedAt);
            throw new \RuntimeException("CJ API circuit breaker is open. Recovery in {$timeToRecovery} seconds");
        }
    }

    /**
     * Record successful API call
     */
    private function recordSuccess(): void
    {
        $key = $this->redisPrefix . 'circuit_breaker';
        
        // Reset circuit breaker on success
        Redis::hmset($key, [
            'state' => 'closed',
            'failure_count' => 0,
            'last_success' => now()->timestamp,
        ]);

        // Update metrics
        $this->incrementMetric('success_count');
    }

    /**
     * Record failed API call
     */
    private function recordFailure(ApiException $e): void
    {
        $key = $this->redisPrefix . 'circuit_breaker';
        $state = Redis::hgetall($key);
        
        $failureCount = (int) ($state['failure_count'] ?? 0) + 1;
        
        Redis::hmset($key, [
            'failure_count' => $failureCount,
            'last_failure' => now()->timestamp,
            'last_failure_type' => $e->status,
        ]);

        // Open circuit breaker if threshold exceeded
        if ($failureCount >= self::CIRCUIT_BREAKER_THRESHOLD) {
            Redis::hmset($key, [
                'state' => 'open',
                'opened_at' => now()->timestamp,
            ]);
            
            Log::warning('CJ API circuit breaker opened', [
                'failure_count' => $failureCount,
                'last_error' => $e->getMessage(),
            ]);
        }

        // Update metrics
        $this->incrementMetric('failure_count');
        $this->incrementMetric('failure_' . $e->status);
    }

    /**
     * Get current request count for monitoring
     */
    private function getCurrentRequestCount(): int
    {
        $key = $this->redisPrefix . 'rate_limit';
        $window = floor(now()->timestamp / self::WINDOW_SIZE) * self::WINDOW_SIZE;
        
        Redis::zremrangebyscore($key, 0, $window - 1);
        return Redis::zcard($key);
    }

    /**
     * Increment metrics for monitoring
     */
    private function incrementMetric(string $metric): void
    {
        $key = $this->redisPrefix . 'metrics:' . $metric;
        Redis::incr($key);
        Redis::expire($key, 86400); // Keep for 24 hours
    }

    /**
     * Get API metrics for monitoring dashboard
     */
    public function getMetrics(): array
    {
        $pattern = $this->redisPrefix . 'metrics:*';
        $keys = Redis::keys($pattern);
        $metrics = [];

        foreach ($keys as $key) {
            $metric = str_replace($this->redisPrefix . 'metrics:', '', $key);
            $metrics[$metric] = (int) Redis::get($key);
        }

        $circuitBreaker = Redis::hgetall($this->redisPrefix . 'circuit_breaker');
        $currentRequests = $this->getCurrentRequestCount();

        return [
            'metrics' => $metrics,
            'circuit_breaker' => $circuitBreaker,
            'current_requests' => $currentRequests,
            'rate_limit' => self::RATE_LIMIT,
            'burst_capacity' => self::BURST_CAPACITY,
        ];
    }

    /**
     * Reset circuit breaker (for admin use)
     */
    public function resetCircuitBreaker(): void
    {
        $key = $this->redisPrefix . 'circuit_breaker';
        Redis::del($key);
        Log::info('CJ API circuit breaker manually reset');
    }
}
