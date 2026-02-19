<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Services\CjPidClaimService;
use Illuminate\Cache\RateLimiter;
use Closure;

class CjRateLimitMiddleware
{
    private RateLimiter $limiter;
    private CjPidClaimService $claimService;

    public function __construct(RateLimiter $limiter, CjPidClaimService $claimService)
    {
        $this->limiter = $limiter;
        $this->claimService = $claimService;
    }

    public function handle(object $job, Closure $next): mixed
    {
        // Only apply to CJ API jobs
        if (!$this->isCjJob($job)) {
            return $next($job);
        }

        $key = $this->getRateLimitKey($job);
        
        // Try to acquire rate limit slot
        if (!$this->limiter->attempt($key, 6, function () {
            return true;
        }, 1)) {
            // Calculate delay based on current rate limit status
            $availableIn = $this->limiter->availableIn($key);
            
            if ($availableIn > 0) {
                $job->release($availableIn);
                return false;
            }
        }

        try {
            return $next($job);
        } finally {
            // Ensure claims are cleaned up
            $this->cleanupClaims($job);
        }
    }

    private function isCjJob(object $job): bool
    {
        return str_contains(class_basename($job), 'Cj') && 
               str_contains(class_basename($job), 'Job');
    }

    private function getRateLimitKey(object $job): string
    {
        return 'cj-api:' . gethostname();
    }

    private function cleanupClaims(object $job): void
    {
        // This is handled by ReleaseCjClaim middleware
        // Keeping as placeholder for additional cleanup if needed
    }
}
