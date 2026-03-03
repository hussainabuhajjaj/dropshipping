<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Jobs\FixStockZeroJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ServerFixStockZero extends Command
{
    protected $signature = 'cj:server-fix-stock-zero
        {--batch=500 : Number of variants to process in this run}
        {--hours=48 : Only fix variants synced in the last X hours}
        {--delay=150 : Delay between API calls in milliseconds}
        {--force : Force fix all stock=0 variants regardless of sync time}
        {--dry-run : Show what would be fixed without actually updating}
        {--queue : Dispatch to queue instead of processing synchronously}
        {--auto-retry : Automatically retry failed variants}
        {--validate : Validate stock sync accuracy after processing}
        {--monitor : Show detailed monitoring statistics}';

    protected $description = 'Production-ready command to fix stock=0 variants with proper error handling and queue support.';

    public function handle(): int
    {
        $batch = (int) $this->option('batch');
        $hours = (int) $this->option('hours');
        $delay = (int) $this->option('delay');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $useQueue = $this->option('queue');
        $autoRetry = $this->option('auto-retry');
        $validate = $this->option('validate');
        $monitor = $this->option('monitor');

        $this->info('=== Production Stock Zero Fix ===');
        $this->info("Batch size: {$batch}");
        $this->info("Hours filter: {$hours}");
        $this->info("API delay: {$delay}ms");
        $this->info("Force mode: " . ($force ? 'Yes' : 'No'));
        $this->info("Dry run: " . ($dryRun ? 'Yes' : 'No'));
        $this->info("Use queue: " . ($useQueue ? 'Yes' : 'No'));
        $this->info("Auto retry: " . ($autoRetry ? 'Yes' : 'No'));
        $this->info("Validate: " . ($validate ? 'Yes' : 'No'));
        $this->info("Monitor: " . ($monitor ? 'Yes' : 'No'));
        $this->line('');

        // Show monitoring statistics if requested
        if ($monitor) {
            $this->showMonitoringStatistics();
            $this->line('');
        }

        // Build query
        $query = ProductVariant::query()
            ->whereNotNull('cj_vid')
            ->where('cj_vid', '!=', '')
            ->where('stock_on_hand', 0);


        if (!$force) {
            $query->where('cj_stock_synced_at', '>=', now()->subHours($hours));
            $this->info("Filtering variants synced in last {$hours} hours");
        } else {
            $this->info('Processing ALL variants with stock=0');
        }

        $totalMatching = $query->count();
        $this->info("Found {$totalMatching} variants with stock=0");

        if ($totalMatching === 0) {
            $this->info('No variants need fixing.');
            return self::SUCCESS;
        }

        if ($useQueue) {
            return $this->processViaQueue($batch, $hours, $delay, $totalMatching);
        }

        return $this->processSynchronously($query, $batch, $delay, $dryRun, $autoRetry, $totalMatching, $validate);
    }

    private function processViaQueue(int $batch, int $hours, int $delay, int $totalMatching): int
    {
        $this->info("Dispatching jobs to queue for processing...");

        $jobCount = ceil($totalMatching / $batch);
        $this->info("Will dispatch {$jobCount} job(s) to process {$totalMatching} variants");

        if ($this->confirm('Continue dispatching jobs to queue?')) {
            for ($i = 0; $i < $jobCount; $i++) {
                FixStockZeroJob::dispatch($batch, $hours, $delay);
                $this->line("Dispatched job " . ($i + 1) . " of {$jobCount}");
            }

            $this->info("All jobs dispatched to 'cj-sync' queue");
            $this->info("Monitor with: php artisan queue:monitor cj-sync");
            $this->info("Process with: php artisan queue:work --queue=cj-sync");
        }

        return self::SUCCESS;
    }

    private function processSynchronously($query, int $batch, int $delay, bool $dryRun, bool $autoRetry, int $totalMatching, bool $validate): int
    {
        // Get batch of variants
        $variants = $query->orderBy('cj_stock_synced_at', 'desc')
            ->limit($batch)
            ->get(['id', 'cj_vid', 'cj_stock_synced_at']);

        $this->info("Processing batch of " . $variants->count() . " variants");
        $this->line('');

        $client = new CJDropshippingClient();
        $totalUpdated = 0;
        $totalErrors = 0;
        $totalSkipped = 0;
        $failedVids = [];

        $progressBar = $this->output->createProgressBar($variants->count());
        $progressBar->start();

        foreach ($variants as $variant) {
            try {
                $progressBar->advance();

                $result = $this->fixVariant($client, $variant, $dryRun);

                if ($result['updated']) {
                    $totalUpdated++;
                } elseif ($result['skipped']) {
                    $totalSkipped++;
                } else {
                    $totalErrors++;
                    $failedVids[] = $variant->cj_vid;
                }

                // Delay to avoid rate limiting
                if ($delay > 0) {
                    usleep($delay * 1000);
                }

            } catch (\Exception $e) {
                $totalErrors++;
                $failedVids[] = $variant->cj_vid;
                Log::error('Stock zero fix failed', [
                    'cj_vid' => $variant->cj_vid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('=== Results ===');
        $this->info("Total processed: {$variants->count()}");
        $this->info("Total updated: {$totalUpdated}");
        $this->info("Total skipped: {$totalSkipped}");
        $this->info("Total errors: {$totalErrors}");

        if (!empty($failedVids) && $autoRetry) {
            $this->newLine();
            $this->info('=== Auto Retry ===');
            $this->info("Retrying " . count($failedVids) . " failed variants...");

            // Retry failed variants with smaller delay
            foreach ($failedVids as $vid) {
                try {
                    $variant = ProductVariant::where('cj_vid', $vid)->first();
                    if ($variant) {
                        $result = $this->fixVariant($client, $variant, $dryRun);
                        if ($result['updated']) {
                            $totalUpdated++;
                            $totalErrors--;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Retry failed', ['cj_vid' => $vid, 'error' => $e->getMessage()]);
                }
            }

            $this->info("Retry completed. Final updated: {$totalUpdated}, Errors: {$totalErrors}");
        }

        if ($variants->count() >= $batch) {
            $this->newLine();
            $this->info("Note: Processed maximum batch size. Run again to continue.");
            $this->info("Remaining variants: " . max(0, $totalMatching - $batch));
        }

        // Log summary
        Log::info('Stock zero fix batch completed', [
            'batch_size' => $variants->count(),
            'updated' => $totalUpdated,
            'skipped' => $totalSkipped,
            'errors' => $totalErrors,
            'dry_run' => $dryRun,
        ]);

        // Run validation if requested
        if ($validate && !$dryRun && $totalUpdated > 0) {
            $this->line('');
            $this->info('=== Validation Results ===');
            $this->validateStockUpdates($variants->pluck('cj_vid')->filter()->all());
        }

        return self::SUCCESS;
    }

    private function fixVariant(CJDropshippingClient $client, ProductVariant $variant, bool $dryRun): array
    {
        // Get stock from CJ API
        $resp = $client->getStockByVid($variant->cj_vid);
        $data = $resp->data ?? null;

        // Calculate stock
        $totalInventory = 0;
        $foundValidData = false;

        if (is_array($data) && isset($data[0])) {
            $row = $data[0];
            if (is_array($row)) {
                $val = $row['totalInventoryNum'] ?? null;
                if ($val === null) $val = $row['totalInventory'] ?? null;
                if ($val === null) $val = $row['storageNum'] ?? null;
                if ($val === null) $val = $row['inventory'] ?? null;

                if (is_numeric($val)) {
                    $totalInventory = (int) $val;
                    $foundValidData = true;
                }
            }
        }

        if ($totalInventory > 0 && $foundValidData) {
            if (!$dryRun) {
                // Update variant
                $updated = ProductVariant::query()
                    ->where('cj_vid', $variant->cj_vid)
                    ->update([
                        'cj_stock' => $totalInventory,
                        'stock_on_hand' => $totalInventory,
                        'cj_stock_synced_at' => now(),
                    ]);

                Log::debug('Fixed stock zero variant', [
                    'cj_vid' => $variant->cj_vid,
                    'old_stock' => 0,
                    'new_stock' => $totalInventory,
                    'updated' => $updated,
                ]);

                return ['updated' => $updated > 0, 'skipped' => false];
            } else {
                $this->newLine();
                $this->line("DRY RUN: Would update VID {$variant->cj_vid} from 0 to {$totalInventory}");
                return ['updated' => true, 'skipped' => false];
            }
        }

        return ['updated' => false, 'skipped' => true];
    }

    /**
     * Show monitoring statistics
     */
    private function showMonitoringStatistics(): void
    {
        $this->info('=== Stock Sync Monitoring Statistics ===');

        $stats = [
            'total_variants' => ProductVariant::whereNotNull('cj_vid')->count(),
            'zero_stock_variants' => ProductVariant::whereNotNull('cj_vid')->where('stock_on_hand', 0)->count(),
            'recently_synced' => ProductVariant::whereNotNull('cj_vid')
                ->where('cj_stock_synced_at', '>=', now()->subHours(24))
                ->count(),
            'never_synced' => ProductVariant::whereNotNull('cj_vid')
                ->whereNull('cj_stock_synced_at')
                ->count(),
            'sync_errors' => ProductVariant::whereNotNull('cj_vid')
                ->where('cj_stock_synced_at', '>=', now()->subHours(24))
                ->where('stock_on_hand', 0)
                ->count(),
        ];

        $this->table(
            ['Metric', 'Count', 'Percentage'],
            [
                ['Total CJ Variants', $stats['total_variants'], '100%'],
                ['Zero Stock Variants', $stats['zero_stock_variants'], 
                    $stats['total_variants'] > 0 ? round(($stats['zero_stock_variants'] / $stats['total_variants']) * 100, 2) . '%' : '0%'],
                ['Recently Synced (24h)', $stats['recently_synced'],
                    $stats['total_variants'] > 0 ? round(($stats['recently_synced'] / $stats['total_variants']) * 100, 2) . '%' : '0%'],
                ['Never Synced', $stats['never_synced'],
                    $stats['total_variants'] > 0 ? round(($stats['never_synced'] / $stats['total_variants']) * 100, 2) . '%' : '0%'],
                ['Potential Sync Errors', $stats['sync_errors'],
                    $stats['total_variants'] > 0 ? round(($stats['sync_errors'] / $stats['total_variants']) * 100, 2) . '%' : '0%'],
            ]
        );

        // Show stock percentage configuration
        $stockPercentage = config('services.cj.stock_percentage', 75.0);
        $this->info("Stock Percentage Configuration: {$stockPercentage}%");
    }

    /**
     * Validate stock updates by re-checking a sample of updated variants
     */
    private function validateStockUpdates(array $vids): void
    {
        if (empty($vids)) {
            $this->info('No variants to validate.');
            return;
        }

        // Sample up to 10 variants for validation
        $sampleVids = array_slice($vids, 0, 10);
        $client = new CJDropshippingClient();
        
        $validationResults = [
            'validated' => 0,
            'accurate' => 0,
            'inaccurate' => 0,
            'errors' => 0,
        ];

        $this->info("Validating " . count($sampleVids) . " variants...");

        foreach ($sampleVids as $vid) {
            try {
                $variant = ProductVariant::where('cj_vid', $vid)->first();
                if (!$variant) {
                    $validationResults['errors']++;
                    continue;
                }

                // Fetch current stock from CJ API
                $response = $client->getStockByVid($vid);
                $data = $response->data ?? [];

                $currentStock = $this->calculateTotalStockFromData($data);
                $expectedStockOnHand = $this->calculateStockOnHand($currentStock);

                $validationResults['validated']++;

                if ($variant->cj_stock === $currentStock && $variant->stock_on_hand === $expectedStockOnHand) {
                    $validationResults['accurate']++;
                    $this->line("✓ VID {$vid}: Stock accurate ({$variant->cj_stock} / {$variant->stock_on_hand})");
                } else {
                    $validationResults['inaccurate']++;
                    $this->line("✗ VID {$vid}: Stock mismatch");
                    $this->line("  Database: {$variant->cj_stock} / {$variant->stock_on_hand}");
                    $this->line("  API: {$currentStock} / {$expectedStockOnHand}");
                }

            } catch (\Exception $e) {
                $validationResults['errors']++;
                $this->line("✗ VID {$vid}: Validation error - " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Validated', $validationResults['validated']],
                ['Accurate', $validationResults['accurate']],
                ['Inaccurate', $validationResults['inaccurate']],
                ['Errors', $validationResults['errors']],
            ]
        );

        if ($validationResults['validated'] > 0) {
            $accuracyRate = round(($validationResults['accurate'] / $validationResults['validated']) * 100, 2);
            $this->info("Validation Accuracy: {$accuracyRate}%");
        }
    }

    /**
     * Calculate total stock from CJ API response data
     */
    private function calculateTotalStockFromData(array $stockData): int
    {
        $totalStock = 0;

        foreach ($stockData as $warehouseStock) {
            if (is_array($warehouseStock)) {
                $stock = $warehouseStock['totalInventoryNum'] ??
                         $warehouseStock['storageNum'] ??
                         $warehouseStock['cjInventoryNum'] ??
                         $warehouseStock['inventory'] ?? 0;
                $totalStock += (int) $stock;
            }
        }

        return $totalStock;
    }

    /**
     * Calculate stock_on_hand with configurable percentage
     */
    private function calculateStockOnHand(int $totalStock): int
    {
        if ($totalStock <= 0) {
            return 0;
        }

        $percentage = (float) config('services.cj.stock_percentage', 75.0);
        $percentage = max(10.0, min(100.0, $percentage));
        
        return (int) ($totalStock * ($percentage / 100.0));
    }
}
