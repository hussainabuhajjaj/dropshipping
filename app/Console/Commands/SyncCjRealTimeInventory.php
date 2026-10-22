<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncCjRealTimeInventory extends Command
{
    protected $signature = 'cj:sync-realtime-inventory
        {--batch-size=50 : Process variants in batches}
        {--dry-run : Show what would be updated without making changes}
        {--force : Skip confirmation prompts}
        {--pid= : Specific CJ PID to process}
        {--vid= : Specific CJ VID to process}
        {--skip-recent=6 : Skip variants synced within last N hours}';

    protected $description = 'Sync real-time inventory from CJ API for existing products and variants';

    public function handle(): int
    {
        $this->info('🔄 CJ Real-Time Inventory Sync');
        $this->info('==============================');

        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $specificPid = $this->option('pid');
        $specificVid = $this->option('vid');
        $skipRecentHours = (int) $this->option('skip-recent');

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

        if ($skipRecentHours > 0) {
            $query->where(function ($q) use ($skipRecentHours) {
                $q->whereNull('cj_stock_synced_at')
                  ->orWhere('cj_stock_synced_at', '<', now()->subHours($skipRecentHours));
            });
        }

        $total = $query->count();
        $this->info("Found {$total} variants to process");

        if ($total === 0) {
            $this->info("No variants found matching criteria.");
            return self::SUCCESS;
        }

        if (!$force && !$dryRun) {
            if (!$this->confirm("Process {$total} variants? This may take a while.")) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        $client = new CJDropshippingClient();
        $processed = 0;
        $updated = 0;
        $errors = 0;

        $progress = $this->output->createProgressBar($total);
        $progress->start();

        $query->chunk($batchSize, function ($variants) use ($client, $dryRun, &$processed, &$updated, &$errors, $progress) {
            foreach ($variants as $variant) {
                try {
                    $processed++;
                    
                    // Get complete variant data from CJ API (all fields, no local generation)
                    $response = $client->getVariantByVid($variant->cj_vid);
                    
                    if (!$response->ok) {
                        $this->line("\n❌ API Error for VID {$variant->cj_vid}: " . $response->message);
                        $errors++;
                        $progress->advance();
                        continue;
                    }

                    $variantData = $response->data ?? [];
                    
                    // Extract ALL variant data from CJ API response
                    $cjApiData = $this->extractCompleteVariantData($variantData);
                    
                    // Compare with current data
                    $changes = $this->compareVariantData($variant, $cjApiData);
                    
                    if (!empty($changes)) {
                        if ($dryRun) {
                            $this->line("\n[DRY RUN] Would update variant {$variant->cj_vid}:");
                            $this->line("  PID: {$variant->product->cj_pid}");
                            foreach ($changes as $field => $change) {
                                $this->line("  {$field}: '{$change['old']}' → '{$change['new']}'");
                            }
                        } else {
                            // Update with ALL data from CJ API
                            $variant->update(array_merge($cjApiData, [
                                'cj_stock_synced_at' => now(),
                            ]));

                            // Update product stock if needed
                            $this->updateProductStock($variant->product);

                            Log::info('Complete variant data synced from CJ API', [
                                'cj_pid' => $variant->product->cj_pid,
                                'cj_vid' => $variant->cj_vid,
                                'fields_updated' => array_keys($changes),
                                'changes' => $changes,
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

        $this->info("\n\n📊 Sync Summary:");
        $this->table(['Metric', 'Count'], [
            ['Processed', $processed],
            ['Updated', $updated],
            ['Errors', $errors],
        ]);

        if ($dryRun) {
            $this->info("\nRun without --dry-run to apply these updates.");
        } else {
            $this->info("\n✅ Real-time inventory sync completed!");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function extractCompleteVariantData(mixed $variantData): array
    {
        if (!is_array($variantData)) {
            return [];
        }

        // Handle different response structures
        if (isset($variantData[0]) && is_array($variantData[0])) {
            $data = $variantData[0];
        } else {
            $data = $variantData;
        }

        $updateData = [];

        // SKU from CJ API (no local generation)
        $updateData['sku'] = $this->extractSkuFromResponse($data);

        // Stock data from CJ API
        $stockData = $this->extractStockDataFromResponse($data);
        $updateData = array_merge($updateData, $stockData);

        // Dimension data from CJ API
        $dimensionData = $this->extractDimensionDataFromResponse($data);
        $updateData = array_merge($updateData, $dimensionData);

        // Title from CJ API
        if (isset($data['variantName']) && !empty($data['variantName'])) {
            $updateData['title'] = (string) $data['variantName'];
        }

        // Image from CJ API
        if (isset($data['variantImage']) && !empty($data['variantImage'])) {
            $updateData['variant_image'] = (string) $data['variantImage'];
        }

        // NOTE: Price data NOT extracted from CJ API - using local margin-based pricing

        // Store raw CJ API response
        $updateData['cj_variant_data'] = $data;

        return $updateData;
    }

    private function extractStockDataFromResponse(array $data): array
    {
        $stockData = [];
        
        // Extract stock from inventories structure first
        if (isset($data['inventories']) && is_array($data['inventories'])) {
            $defaultWarehouse = env('CJ_DEFAULT_WAREHOUSE', 'CN');
            
            foreach ($data['inventories'] as $inventory) {
                if (!is_array($inventory)) continue;
                
                if (($inventory['countryCode'] ?? null) === $defaultWarehouse) {
                    $stockData['cj_stock'] = (int) ($inventory['totalInventory'] ?? $inventory['cjInventory'] ?? 0);
                    break;
                }
            }
        }
        
        // Legacy stock fields
        if (!isset($stockData['cj_stock'])) {
            $stockFields = ['totalInventoryNum', 'totalInventory', 'inventoryNum', 'cjInventory', 'variantStock', 'stock'];
            
            foreach ($stockFields as $field) {
                if (isset($data[$field]) && is_numeric($data[$field])) {
                    $stockData['cj_stock'] = (int) $data[$field];
                    break;
                }
            }
        }
        
        // Calculate stock_on_hand from cj_stock
        if (isset($stockData['cj_stock'])) {
            $percentage = (float) config('services.cj.stock_percentage', 75.0);
            $percentage = max(10.0, min(100.0, $percentage));
            $stockData['stock_on_hand'] = (int) ($stockData['cj_stock'] * ($percentage / 100.0));
        }
        
        return $stockData;
    }

    private function extractDimensionDataFromResponse(array $data): array
    {
        $dimensionData = [];
        
        // Dimensions in millimeters
        if (isset($data['variantLength']) && is_numeric($data['variantLength'])) {
            $dimensionData['package_length_mm'] = (int) $data['variantLength'];
        }
        
        if (isset($data['variantWidth']) && is_numeric($data['variantWidth'])) {
            $dimensionData['package_width_mm'] = (int) $data['variantWidth'];
        }
        
        if (isset($data['variantHeight']) && is_numeric($data['variantHeight'])) {
            $dimensionData['package_height_mm'] = (int) $data['variantHeight'];
        }
        
        // Weight in grams
        if (isset($data['variantWeight']) && is_numeric($data['variantWeight'])) {
            $dimensionData['weight_grams'] = (int) $data['variantWeight'];
        }
        
        return $dimensionData;
    }

    private function compareVariantData(ProductVariant $variant, array $cjApiData): array
    {
        $changes = [];
        
        foreach ($cjApiData as $field => $newValue) {
            if ($field === 'cj_variant_data') {
                continue; // Skip raw data field
            }
            
            $oldValue = $variant->{$field};
            
            if ($oldValue != $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }
        
        return $changes;
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

    private function extractStockFromResponse(mixed $stockData): int
    {
        if (!is_array($stockData)) {
            return 0;
        }

        // Handle different response structures
        if (isset($stockData[0]) && is_array($stockData[0])) {
            $data = $stockData[0];
        } else {
            $data = $stockData;
        }

        // Priority order for stock fields
        $stockFields = [
            'totalInventoryNum',
            'totalInventory',
            'inventoryNum',
            'cjInventory',
            'stock',
            'variantStock',
            'quantity'
        ];

        foreach ($stockFields as $field) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                return (int) $data[$field];
            }
        }

        return 0;
    }

    private function calculateStockOnHand(int $totalStock): int
    {
        if ($totalStock <= 0) {
            return 0;
        }

        $percentage = (float) config('services.cj.stock_percentage', 75.0);
        $percentage = max(10.0, min(100.0, $percentage));
        
        return (int) ($totalStock * ($percentage / 100.0));
    }

    private function updateProductStock(Product $product): void
    {
        $variantsStock = $product->variants()
            ->whereNotNull('cj_vid')
            ->pluck('stock_on_hand');

        if ($variantsStock->isNotEmpty()) {
            $totalStock = $variantsStock->sum();
            $avgStock = (int) ($variantsStock->avg());

            $product->update([
                'stock_on_hand' => $avgStock,
                'cj_total_stock' => $totalStock,
                'cj_synced_at' => now(),
            ]);
        }
    }
}
