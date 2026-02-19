<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CjApiRateLimiterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CjApiMonitoringController extends Controller
{
    public function __construct(private CjApiRateLimiterService $rateLimiter)
    {
    }

    public function metrics(Request $request): JsonResponse
    {
        try {
            $metrics = $this->rateLimiter->getMetrics();
            
            return response()->json([
                'status' => 'success',
                'data' => $metrics,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch CJ API metrics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch metrics',
            ], 500);
        }
    }

    public function resetCircuitBreaker(Request $request): JsonResponse
    {
        try {
            $this->rateLimiter->resetCircuitBreaker();
            
            Log::info('CJ API circuit breaker reset via admin interface', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Circuit breaker reset successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reset CJ API circuit breaker', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reset circuit breaker',
            ], 500);
        }
    }

    public function health(Request $request): JsonResponse
    {
        try {
            $metrics = $this->rateLimiter->getMetrics();
            $circuitBreaker = $metrics['circuit_breaker'] ?? [];
            $state = $circuitBreaker['state'] ?? 'closed';
            
            $isHealthy = $state !== 'open';
            $status = $isHealthy ? 'healthy' : 'degraded';
            $httpStatus = $isHealthy ? 200 : 503;

            return response()->json([
                'status' => $status,
                'circuit_breaker_state' => $state,
                'current_requests' => $metrics['current_requests'] ?? 0,
                'rate_limit' => $metrics['rate_limit'] ?? 6,
                'timestamp' => now()->toISOString(),
            ], $httpStatus);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => 'Health check failed',
            ], 503);
        }
    }
}
