<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCjSkusFromApi extends Command
{
    protected $signature = 'cj:sync-skus-from-api
        {--batch-size=50 : Process variants in batches}
        {--dry-run : Show what would be updated without making changes}
        {--force : Skip confirmation prompts}
        {--pid= : Specific CJ PID to process}
        {--vid= : Specific CJ VID to process}
        {--update-existing : Update existing SKUs from CJ API}
        {--fill-missing : Only fill missing SKUs}
        {--check-cj-prefix : Check CJ prefix SKUs against CJ API data}';

    protected $description = 'Sync variant SKUs from CJ API endpoints';

    public function handle(): int
    {
        $this->info('🏷️  CJ SKU Sync from API');
        $this->info('========================');

        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $specificPid = $this->option('pid');
        $specificVid = $this->option('vid');
        $updateExisting = $this->option('update-existing');
        $fillMissing = $this->option('fill-missing');
        $checkCjPrefix = $this->option('check-cj-prefix');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        // Build query
        $query = ProductVariant::whereNotNull('cj_vid')
            ->whereHas('product', function ($q) {
                $q->whereNotNull('cj_pid');
            });

        // Apply filters
        if ($specificPid) {
            $query->whereHas('product', function ($q) use ($specificPid) {
                $q->where('cj_pid', $specificPid);
            });
        }

        if ($specificVid) {
            $query->where('cj_vid', $specificVid);
        }

        if ($fillMissing) {
            $query->where(function ($q) {
                $q->whereNull('sku')
                  ->orWhere('sku', '')
                  ->orWhereRaw('sku = " "')
                  ->orWhereRaw('sku LIKE " %"')
                  ->orWhereRaw('sku LIKE "% "')
                  ->orWhereRaw('sku LIKE "% %"');
            });
        }

        if ($checkCjPrefix) {
            $query->where('sku', 'like', 'CJ%')
                  ->where('sku', 'not like', 'CJ-%'); // CJ prefix but not CJ-VID pattern
        }

        $total = $query->count();
        $this->info("Found {$total} variants to process");

        if ($total === 0) {
            $this->info("No variants found matching criteria.");
            return self::SUCCESS;
        }

        if (!$force && !$dryRun) {
            if (!$this->confirm("Process {$total} variants? This will sync SKUs from CJ API.")) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        $client = new CJDropshippingClient();
        $processed = 0;
        $updated = 0;
        $errors = 0;
        $skusFromApi = 0;
        $skusMissing = 0;

        $progress = $this->output->createProgressBar($total);
        $progress->start();

        $query->chunk($batchSize, function ($variants) use ($client, $dryRun, &$processed, &$updated, &$errors, &$skusFromApi, &$skusMissing, $progress, $updateExisting) {
            foreach ($variants as $variant) {
                try {
                    $processed++;
                    
                    // Get variant data from CJ API
                    $response = $client->getVariantByVid($variant->cj_vid);
                    
                    if (!$response->ok) {
                        $this->line("\n❌ API Error for VID {$variant->cj_vid}: " . $response->message);
                        $errors++;
                        $progress->advance();
                        continue;
                    }

                    $variantData = $response->data ?? [];
                    $apiSku = $this->extractSkuFromResponse($variantData);
                    
                    $oldSku = $variant->sku;
                    $needsUpdate = false;

                    // Determine if update is needed
                    if ($apiSku) {
                        $skusFromApi++;
                        
                        if ($updateExisting || !$oldSku || $oldSku === 'CJ-' . $variant->cj_vid) {
                            if ($oldSku !== $apiSku) {
                                $needsUpdate = true;
                            }
                        }
                    } else {
                        $skusMissing++;
                        if (!$oldSku) {
                            // Generate fallback SKU only if completely missing
                            $apiSku = 'CJ-' . $variant->cj_vid;
                            $needsUpdate = true;
                        }
                    }

                    if ($needsUpdate) {
                        if ($dryRun) {
                            $this->line("\n[DRY RUN] Would update variant {$variant->cj_vid}:");
                            $this->line("  PID: {$variant->product->cj_pid}");
                            $this->line("  SKU: '{$oldSku}' → '{$apiSku}'");
                            $this->line("  Source: " . ($apiSku && $apiSku !== 'CJ-' . $variant->cj_vid ? 'CJ API' : 'Generated'));
                        } else {
                            $variant->update([
                                'sku' => $apiSku,
                                'cj_stock_synced_at' => now(), // Update sync timestamp
                            ]);

                            Log::info('Variant SKU synced from CJ API', [
                                'cj_pid' => $variant->product->cj_pid,
                                'cj_vid' => $variant->cj_vid,
                                'old_sku' => $oldSku,
                                'new_sku' => $apiSku,
                                'source' => ($apiSku && $apiSku !== 'CJ-' . $variant->cj_vid) ? 'CJ_API' : 'Generated',
                            ]);
                        }
                        $updated++;
                    }

                } catch (\Exception $e) {
                    $this->line("\n❌ Error processing VID {$variant->cj_vid}: " . $e->getMessage());
                    $errors++;
                }

                $progress->advance();
            }
        });

        $progress->finish();

        $this->info("\n\n📊 SKU Sync Summary:");
        $this->table(['Metric', 'Count'], [
            ['Processed', $processed],
            ['Updated', $updated],
            ['Errors', $errors],
            ['SKUs from CJ API', $skusFromApi],
            ['SKUs missing in API', $skusMissing],
        ]);

        if ($dryRun) {
            $this->info("\nRun without --dry-run to apply these updates.");
        } else {
            $this->info("\n✅ SKU sync completed!");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function extractSkuFromResponse(mixed $variantData): ?string
    {
        if (!is_array($variantData)) {
            return null;
        }

        // Handle different response structures
        if (isset($variantData[0]) && is_array($variantData[0])) {
            $data = $variantData[0];
        } else {
            $data = $variantData;
        }

        // Priority order for SKU fields from CJ API
        $skuFields = [
            'variantSku',      // Primary CJ SKU field
            'sku',             // Generic SKU field
            'vSku',            // Alternative CJ SKU field
            'variantCode',     // Variant code field
            'itemSku',         // Item SKU field
        ];

        foreach ($skuFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                return (string) trim($data[$field]);
            }
        }

        return null;
    }
}
