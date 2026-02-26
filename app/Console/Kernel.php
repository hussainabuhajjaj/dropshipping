<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\CjSyncCatalog;
use App\Console\Commands\TranslateProducts;
use App\Console\Commands\TranslateCategories;
use App\Console\Commands\SyncCjVariants;
use App\Console\Commands\CjBatchSyncVariantsCommand;
use App\Jobs\CheckLowStockJob;
use App\Jobs\FlagShipmentsAtRisk;
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
        TranslateCategories::class,
        \App\Console\Commands\TranslateMobileStrings::class,
        \App\Console\Commands\CjCleanupWebhooks::class,
        \App\Console\Commands\CjRefreshToken::class,
        SyncCjVariants::class,
        \App\Console\Commands\CjFixProductDetails::class,
        CjBatchSyncVariantsCommand::class,
        \App\Console\Commands\FixCorruptedMargins::class,
        \App\Console\Commands\MonitorPriceCorruption::class,
        \App\Console\Commands\CreateAffiliateCommand::class,
        \App\Console\Commands\CreateAffiliateUserCommand::class,
        \App\Console\Commands\GenerateAffiliateReportsCommand::class,
        \App\Console\Commands\ReconcileAffiliateCommissionsCommand::class,
        \App\Console\Commands\CjSyncStockByVid::class,
        \App\Console\Commands\CjSyncMedia::class,
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

        // Flag shipments that have no tracking updates for too long
        $schedule->job(new FlagShipmentsAtRisk())->dailyAt('05:30');
        
        // Monitor for price corruption every 30 minutes
        $schedule->command('pricing:monitor-corruption --alert-threshold=5000')->everyThirtyMinutes();

        // Sync CJ stock by VID nightly at 23:59
        $schedule->command('cj:sync-stock-by-vid --limit=10000 --stale-minutes=30')->dailyAt('23:59');
        // Sync CJ media (images & videos) for all sync-enabled products daily at 03:00
        $schedule->command('cj:sync-media --chunk=20')->dailyAt('03:00');
        // Sync CJ variants via chunked jobs daily at 02:45
        $schedule->command('cj:sync-variants')->dailyAt('02:45');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
