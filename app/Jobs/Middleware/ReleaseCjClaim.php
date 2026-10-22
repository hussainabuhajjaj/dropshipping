<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Services\CjPidClaimService;
use Psr\Log\LoggerInterface as LogContract;

class ReleaseCjClaim
{
    public function handle($job, $next)
    {
        $claimService = app(CjPidClaimService::class);
        $logger = app(LogContract::class);

        try {
            return $next($job);
        } catch (\Throwable $e) {
            // Ensure claims are released even when job throws
            $this->releaseClaimsFromJob($job, $claimService, $logger);
            throw $e;
        } finally {
            // Always attempt to release any claims belonging to this job
            $this->releaseClaimsFromJob($job, $claimService, $logger);
        }
    }

    private function releaseClaimsFromJob($job, CjPidClaimService $claimService, LogContract $logger): void
    {
        // Check for common properties that may carry claim tokens
        if (property_exists($job, 'products') && is_array($job->products)) {
            foreach ($job->products as $item) {
                $pid = (string) ($item['id'] ?? '');
                $token = (string) ($item['_cj_claim_token'] ?? '');
                if ($pid !== '' && $token !== '') {
                    try {
                        $claimService->release($pid, $token);
                    } catch (\Throwable $ee) {
                        $logger->warning('Failed to release claim in middleware', ['pid' => $pid, 'error' => $ee->getMessage()]);
                    }
                }
            }
        }

        // For completeness, support jobs that may expose a single pid/token
        if (property_exists($job, 'pid') && property_exists($job, 'token')) {
            $pid = (string) ($job->pid ?? '');
            $token = (string) ($job->token ?? '');
            if ($pid !== '' && $token !== '') {
                try {
                    $claimService->release($pid, $token);
                } catch (\Throwable $ee) {
                    $logger->warning('Failed to release single claim in middleware', ['pid' => $pid, 'error' => $ee->getMessage()]);
                }
            }
        }
    }
}
