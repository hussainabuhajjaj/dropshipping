<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Queue\QueueHealthReportService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class QueueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Queue::after(function (JobProcessed $event): void {
            try {
                app(QueueHealthReportService::class)->recordProcessed($event);
            } catch (\Throwable $e) {
                Log::warning('Failed to record queue processed metric', ['error' => $e->getMessage()]);
            }
        });

        Queue::failing(function (JobFailed $event) {
            Log::error('Queue job failed', [
                'job' => $event->job?->resolveName(),
                'queue' => $event->job?->getQueue(),
                'exception' => $event->exception->getMessage(),
                'trace' => $event->exception->getTraceAsString(),
            ]);

            try {
                app(QueueHealthReportService::class)->recordFailed($event);
            } catch (\Throwable $e) {
                Log::warning('Failed to record queue failed metric', ['error' => $e->getMessage()]);
            }
        });
    }
}
