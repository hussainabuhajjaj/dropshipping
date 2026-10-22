<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\CjPidClaimService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Queue\Events\WorkerStopping;

class CjClaimServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CjPidClaimService::class, function ($app) {
            return new CjPidClaimService();
        });
    }

    public function boot(): void
    {
        // When a worker is stopping, attempt to release all claims owned by this process
        Event::listen(WorkerStopping::class, function (WorkerStopping $event) {
            try {
                $owner = gethostname() . ':' . getmypid();
                $claimService = app(CjPidClaimService::class);
                $released = $claimService->releaseAllForOwner($owner);
                logger()->info('Released CJ PID claims on worker stopping', ['owner' => $owner, 'released' => $released]);
            } catch (\Throwable $e) {
                logger()->warning('Failed to release CJ PID claims on worker stopping', ['error' => $e->getMessage()]);
            }
        });
    }
}
