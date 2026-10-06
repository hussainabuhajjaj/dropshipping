<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\CjSyncCatalog;
use App\Console\Commands\TranslateProducts;
use App\Console\Commands\TranslateCategories;
use App\Console\Commands\SyncCjVariants;
use App\Console\Commands\CjBatchSyncVariantsCommand;
use App\Console\Commands\UpdateStockFromMetadata;
use App\Console\Commands\FixDefaultCategories;
use App\Console\Commands\FixDefaultVariants;
use App\Console\Commands\ResyncCheckVariants;
use App\Console\Commands\ReconcilePaymentsCommand;
use App\Console\Commands\DailyInstagramProducts;
use App\Console\Commands\DailySalesReport;
use App\Console\Commands\HealthCheckCampaignsCommand;
use App\Jobs\CheckLowStockJob;
use App\Jobs\FlagShipmentsAtRisk;
use App\Jobs\ProcessAbandonedCartsJob;
use App\Jobs\RequestProductReviewJob;
use App\Jobs\SendAbandonedCartReminders;
use App\Jobs\SendPostPurchaseCrossSells;
use App\Jobs\SendWinBackReminders;
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
        \App\Console\Commands\ReimportProductsAndVariants::class,
        \App\Console\Commands\FixCorruptedMargins::class,
        \App\Console\Commands\MonitorPriceCorruption::class,
        \App\Console\Commands\CreateAffiliateCommand::class,
        \App\Console\Commands\CreateAffiliateUserCommand::class,
        \App\Console\Commands\GenerateAffiliateReportsCommand::class,
        \App\Console\Commands\ReconcileAffiliateCommissionsCommand::class,
        \App\Console\Commands\CjSyncStockByVid::class,
        \App\Console\Commands\MonitorPaymentHealth::class,
        \App\Console\Commands\SetupPaymentMonitoring::class,
        \App\Console\Commands\ServerFixStockZero::class,
        \App\Console\Commands\CjSyncMedia::class,
        \App\Console\Commands\CjInspectOrder::class,
        \App\Console\Commands\CjReconnectOrder::class,
        \App\Console\Commands\CjTestSourcingLookup::class,
        \App\Console\Commands\InspectStorefrontCollection::class,
        UpdateStockFromMetadata::class,
        FixDefaultCategories::class,
        FixDefaultVariants::class,
        ResyncCheckVariants::class,
        \App\Console\Commands\PushStorefrontAnnouncement::class,
        \App\Console\Commands\BackfillLogisticsRecords::class,
        ReconcilePaymentsCommand::class,
        \App\Console\Commands\ExpireWhatsAppOrderIntents::class,
        DailySalesReport::class,
        HealthCheckCampaignsCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // DISABLED: Daily full catalog sync - too heavy on server resources
        // Using weekly sync instead to prevent database corruption and OOM issues
        // $schedule->call(function () {
        //     $service = app(\App\Domain\Products\Services\CjProductImportService::class);
        //     $result = $service->importBulkWithPipeline([
        //         'margin_percent' => (float) config('services.cj.import_margin', 35),
        //         'enrich' => true,
        //         'skip_existing' => true,
        //     ]);
        //     \Illuminate\Support\Facades\Log::info('CJ daily sync completed', $result);
        // })->dailyAt('00:00')
        //   ->name('cj-daily-import-new')
        //   ->withoutOverlapping();

        // NEW: Weekly full refresh (update all products, no skip-existing)
        $schedule->call(function () {
            $service = app(\App\Domain\Products\Services\CjProductImportService::class);
            $service->importBulkWithPipeline([
                'margin_percent' => (float) config('services.cj.import_margin', 60),
                'enrich' => true,
                'skip_existing' => false, // Update all products weekly
            ]);
        })->weeklyOn(0, '03:00')
            ->name('cj-full-sync-weekly')
            ->withoutOverlapping();

        // REMOVED: Duplicate sync at 02:00 (already covered by cj-full-sync-daily above)
        // $schedule->command('cj:sync-catalog')->dailyAt('02:00');

        // OPTIMIZED: Use new smart stock sync instead of hourly heavy job
        // Old: $schedule->job(new SyncCjInventoryHourly())->hourly();
        $schedule->command('cj:sync-existing-stock --skip-recent=6 --delay=1100')
            ->everySixHours()
            ->name('cj-smart-stock-sync')
            ->withoutOverlapping();

        // DEPRECATED: Old fragmented sync commands (kept for safety, will remove after testing)
        // $schedule->command('cj:sync-variants')->dailyAt('02:30');
        // $schedule->command('cj:sync-media --chunk=20')->dailyAt('03:00');

        // Daily: Import trending products from CJ (women, men, kids)
        $schedule->command('cj:import-trending')
            ->dailyAt('06:00')
            ->name('cj-import-trending')
            ->withoutOverlapping()
            ->description('Import 10 trending + 20 category-specific products daily');

        // Keep: Token refresh
        $schedule->command('cj:refresh-token')->dailyAt('03:30');

        // OPTIMIZED: Reduced from 10k to 1k products, use smart skip
        $schedule->command('cj:sync-existing-stock --skip-recent=24 --delay=1100')
            ->dailyAt('23:59')
            ->name('cj-daily-stock-refresh')
            ->withoutOverlapping();

        // NEW: Automated stock zero fix
        $schedule->job(new \App\Jobs\FixStockZeroJob(500, 48, 150))
            ->everyFourHours()
            ->name('cj-fix-stock-zero')
            ->withoutOverlapping()
            ->description('Fix variants with stock=0 that have actual CJ inventory');

        // Keep: Other non-CJ jobs
        $schedule->job(new CheckLowStockJob())->dailyAt('04:00');
        $schedule->job(new \App\Jobs\CalculateCustomerLTVJob())->weekly()->sundays()->at('01:00');
        $schedule->job(new SendAbandonedCartReminders())->everyThirtyMinutes();
        $schedule->job(new RequestProductReviewJob())->dailyAt('09:00');
        $schedule->job(new SendPostPurchaseCrossSells())->dailyAt('10:00');
        $schedule->job(new SendWinBackReminders())->weekly()->mondays()->at('08:00');
        $schedule->job(new \App\Jobs\AutoApproveCjFulfillmentJob())->everyTenMinutes();
        $schedule->command('payments:reconcile')
            ->everyTenMinutes()
            ->name('payments-reconcile-paystack')
            ->withoutOverlapping()
            ->description('Reconcile pending Paystack payments');
        $schedule->command('payments:reconcile-korapay --limit=50 --min-age=3 --max-age=4320')
            ->everyFiveMinutes()
            ->name('payments-reconcile-korapay')
            ->withoutOverlapping()
            ->description('Reconcile pending Korapay payments (redirect-independent)');
        $schedule->job(new FlagShipmentsAtRisk())->dailyAt('05:30');
        $schedule->command('pricing:monitor-corruption --alert-threshold=5000')->everyThirtyMinutes();
        $schedule->command('whatsapp-intents:expire')->everyTenMinutes();
        $schedule->command('dropshipping:daily-instagram')->dailyAt('09:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
