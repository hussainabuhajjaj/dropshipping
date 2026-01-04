<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\CjSyncCatalog;
use App\Console\Commands\TranslateProducts;
use App\Console\Commands\SyncCjVariants;
use App\Jobs\CheckLowStockJob;
use App\Jobs\ProcessAbandonedCartsJob;
use App\Jobs\RequestProductReviewJob;
use App\Jobs\SendAbandonedCartReminders;
use App\Jobs\SyncCjInventoryHourly;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        CjSyncCatalog::class,
        TranslateProducts::class,
        \App\Console\Commands\CjCleanupWebhooks::class,
        \App\Console\Commands\CjRefreshToken::class,
        SyncCjVariants::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('cj:sync-catalog')->dailyAt('02:00');
        // Hourly CJ inventory sync for active products (every hour)
        $schedule->job(new SyncCjInventoryHourly())->hourly();
        // Sync CJ variants and stock levels (runs daily at 02:30)
        $schedule->command('cj:sync-variants')->dailyAt('02:30');
        // Proactive token refresh to avoid expiry gaps (runs daily at 03:30)
        $schedule->command('cj:refresh-token')->dailyAt('03:30');
        // Low stock check and alert email (runs daily at 04:00)
        $schedule->job(new CheckLowStockJob())->dailyAt('04:00');
        // Calculate customer lifetime values (runs weekly on Sunday at 01:00)
        $schedule->job(new \App\Jobs\CalculateCustomerLTVJob())->weekly()->sundays()->at('01:00');
        // Abandoned cart reminder emails (runs every 30 minutes)
        $schedule->job(new SendAbandonedCartReminders())->everyThirtyMinutes();
        // Request product reviews (7 days after delivery)
        $schedule->job(new RequestProductReviewJob())->dailyAt('09:00');

        // Auto-approve pending CJ fulfillment items (every 10 minutes)
        $schedule->job(new \App\Jobs\AutoApproveCjFulfillmentJob())->everyTenMinutes();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
