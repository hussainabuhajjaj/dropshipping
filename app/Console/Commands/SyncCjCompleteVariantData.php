<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCjCompleteVariantData extends Command
{
    protected $signature = 'cj:sync-complete-variant-data
        {--batch-size=25 : Process variants in batches}
        {--dry-run : Show what would be updated without making changes}
        {--force : Skip confirmation prompts}
        {--pid= : Specific CJ PID to process}
        {--vid= : Specific CJ VID to process}
        {--skip-recent=24 : Skip variants synced within last N hours}';

    protected $description = 'Sync ALL variant data from CJ API - no local generation';

    public function handle(): int
    {
        $this->info('🔄 CJ Complete Variant Data Sync');
        $this->info('=================================');

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
        $query = ProductVariant::whereNotNull('cj_vid');

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
            if (!$this->confirm("Process {$total} variants? This will sync ALL data from CJ API.")) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        $client = new CJDropshippingClient();
        $processed = 0;
        $updated = 0;
        $errors = 0;
        $fieldsUpdated = 0;

        $progress = $this->output->createProgressBar($total);
        $progress->start();

        $query->chunk($batchSize, function ($variants) use ($client, $dryRun, &$processed, &$updated, &$errors, &$fieldsUpdated, $progress) {
            foreach ($variants as $variant) {
                try {
                    $processed++;
                    
                    // Get complete variant data from CJ API
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

                            Log::info('Complete variant data synced from CJ API', [
                                'cj_pid' => $variant->product->cj_pid,
                                'cj_vid' => $variant->cj_vid,
                                'fields_updated' => array_keys($changes),
                                'changes' => $changes,
                            ]);
                        }
                        $updated++;
                        $fieldsUpdated += count($changes);
                    }

                } catch (\Exception $e) {
                    $this->line("\n❌ Error processing VID {$variant->cj_vid}: " . $e->getMessage());
                    $errors++;
                }

                $progress->advance();
            }
        });

        $progress->finish();

        $this->info("\n\n📊 Complete Variant Sync Summary:");
        $this->table(['Metric', 'Count'], [
            ['Processed', $processed],
            ['Updated Variants', $updated],
            ['Total Field Updates', $fieldsUpdated],
            ['Errors', $errors],
        ]);

        if ($dryRun) {
            $this->info("\nRun without --dry-run to apply these updates.");
        } else {
            $this->info("\n✅ Complete variant data sync finished!");
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

        // SKU fields from CJ API
        $updateData['sku'] = $this->extractSku($data);

        // Stock data from CJ API
        $stockData = $this->extractStockData($data);
        $updateData = array_merge($updateData, $stockData);

        // Dimensions data from CJ API
        $dimensionData = $this->extractDimensionData($data);
        $updateData = array_merge($updateData, $dimensionData);

        // Title and description from CJ API
        $textData = $this->extractTextData($data);
        $updateData = array_merge($updateData, $textData);

        // Image data from CJ API
        $imageData = $this->extractImageData($data);
        $updateData = array_merge($updateData, $imageData);

        // Options/attributes from CJ API
        $optionsData = $this->extractOptionsData($data);
        $updateData = array_merge($updateData, $optionsData);

        // NOTE: Price data NOT extracted from CJ API - using local margin-based pricing

        // Store raw CJ API response for reference
        $updateData['cj_variant_data'] = $data;
        $updateData['metadata'] = [
            'cj_vid' => $data['vid'] ?? null,
            'cj_variant' => $data,
            'last_api_sync' => now()->toISOString(),
            'sync_source' => 'cj_api_complete',
        ];

        return $updateData;
    }

    private function extractSku(array $data): ?string
    {
        $skuFields = ['variantSku', 'sku', 'vSku', 'variantCode', 'itemSku'];
        
        foreach ($skuFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                return (string) trim($data[$field]);
            }
        }
        
        return null; // Don't generate fallback - let it be null if not in API
    }

    private function extractStockData(array $data): array
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
            
            // Fallback to any warehouse if CN not found
            if (!isset($stockData['cj_stock'])) {
                foreach ($data['inventories'] as $inventory) {
                    if (!is_array($inventory)) continue;
                    $stockValue = $inventory['totalInventory'] ?? $inventory['cjInventory'] ?? 0;
                    if ((int) $stockValue > 0) {
                        $stockData['cj_stock'] = (int) $stockValue;
                        break;
                    }
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

    private function extractDimensionData(array $data): array
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

    private function extractTextData(array $data): array
    {
        $textData = [];
        
        // Title from CJ API
        $titleFields = ['variantName', 'variantNameEn', 'variantTitle', 'title'];
        foreach ($titleFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $textData['title'] = (string) $data[$field];
                break;
            }
        }
        
        return $textData;
    }

    private function extractImageData(array $data): array
    {
        $imageData = [];
        
        // Variant image from CJ API
        $imageFields = ['variantImage', 'image', 'variantImageUrl', 'imageUrl'];
        foreach ($imageFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $imageData['variant_image'] = (string) $data[$field];
                break;
            }
        }
        
        return $imageData;
    }

    private function extractOptionsData(array $data): array
    {
        $optionsData = [];
        
        // Options/attributes from CJ API
        if (isset($data['variantAttributes']) && is_array($data['variantAttributes'])) {
            $optionsData['options'] = $data['variantAttributes'];
        } elseif (isset($data['attributes']) && is_array($data['attributes'])) {
            $optionsData['options'] = $data['attributes'];
        }
        
        return $optionsData;
    }

    private function compareVariantData(ProductVariant $variant, array $cjApiData): array
    {
        $changes = [];
        
        foreach ($cjApiData as $field => $newValue) {
            if ($field === 'cj_variant_data' || $field === 'metadata') {
                continue; // Skip metadata fields
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
}
