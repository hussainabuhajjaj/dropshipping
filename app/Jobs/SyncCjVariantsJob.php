<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

class SyncCjVariantsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;

    public function __construct(
        public string $cjPid,
    ) {
    }

    public function handle(CJDropshippingClient $client): void
    {
        try {
            // Find the product by CJ PID
            $product = Product::where('cj_pid', $this->cjPid)->first();

            if (!$product) {
                Log::warning('Product not found for CJ sync', ['cj_pid' => $this->cjPid]);
                return;
            }

            // Get variants from CJ API
            $resp = $client->getVariantsByPid($this->cjPid);

            // DEBUG: Log the raw CJ API response structure
            Log::info('CJ API raw response structure', [
                'cj_pid' => $this->cjPid,
                'response_data_type' => gettype($resp->data ?? null),
                'response_data_keys' => is_array($resp->data) ? array_keys(array_slice($resp->data, 0, 10, true)) : null,
                'response_data_preview' => is_array($resp->data) ? array_slice($resp->data, 0, 3, true) : $resp->data,
            ]);

            $variants = $this->extractVariants($resp->data ?? null);
//            dd($variants);
            if ($variants === null) {
                $data = $resp->data ?? null;
                Log::warning('No variants found in CJ response', [
                    'cj_pid' => $this->cjPid,
                    'data_type' => gettype($data),
                    'data_keys' => is_array($data) ? array_keys(array_slice($data, 0, 20, true)) : null,
                ]);
                return;
            }

            if ($variants === []) {
                Log::info('CJ response returned empty variants list', ['cj_pid' => $this->cjPid]);
                return;
            }

            foreach ($variants as $variantData) {
                $vid = (string) ($variantData['vid'] ?? '');

                if ($vid === '') {
                    continue;
                }

                // Find existing variant to get current values
                $existingVariant = ProductVariant::where('product_id', $product->id)
                    ->where('cj_vid', $vid)
                    ->first();

                $variantSku = trim((string) ($variantData['variantSku'] ?? $existingVariant?->sku ?? ''));
                $sku = $variantSku !== '' ? $variantSku : 'CJ-' . $vid;

                // Extract stock information from CJ variant data with enhanced logic
                $cjStock = 0;
                $stockDebugInfo = [
                    'vid' => $vid,
                    'has_inventories' => isset($variantData['inventories']),
                    'inventories_type' => gettype($variantData['inventories'] ?? null),
                    'raw_variant_data_keys' => array_keys($variantData),
                    'default_warehouse' => env('CJ_DEFAULT_WAREHOUSE', 'CN'),
                ];
                
                // Handle new inventories structure
                if (isset($variantData['inventories']) && is_array($variantData['inventories'])) {
                    $stockDebugInfo['inventories_count'] = count($variantData['inventories']);
                    
                    foreach ($variantData['inventories'] as $index => $inventory) {
                        $stockDebugInfo['inventory_' . $index] = [
                            'countryCode' => $inventory['countryCode'] ?? 'missing',
                            'totalInventory' => $inventory['totalInventory'] ?? 'missing',
                            'cjInventory' => $inventory['cjInventory'] ?? 'missing',
                            'factoryInventory' => $inventory['factoryInventory'] ?? 'missing',
                            'is_warehouse_match' => ($inventory['countryCode'] ?? null) === env('CJ_DEFAULT_WAREHOUSE', 'CN'),
                        ];
                        
                        if (isset($inventory['countryCode']) && $inventory['countryCode'] === env('CJ_DEFAULT_WAREHOUSE', 'CN')) {
                            $cjStock = (int) ($inventory['totalInventory'] ?? $inventory['cjInventory'] ?? 0);
                            $stockDebugInfo['found_warehouse_stock'] = $cjStock;
                            break;
                        }
                    }
                    
                    // If no CN warehouse found, try ANY warehouse as fallback
                    if ($cjStock === 0 && !empty($variantData['inventories'])) {
                        $stockDebugInfo['cn_warehouse_not_found'] = true;
                        foreach ($variantData['inventories'] as $index => $inventory) {
                            $stockValue = $inventory['totalInventory'] ?? $inventory['cjInventory'] ?? $inventory['factoryInventory'] ?? 0;
                            if ((int) $stockValue > 0) {
                                $cjStock = (int) $stockValue;
                                $stockDebugInfo['fallback_warehouse'] = [
                                    'index' => $index,
                                    'country' => $inventory['countryCode'] ?? 'unknown',
                                    'stock' => $cjStock,
                                ];
                                break;
                            }
                        }
                    }
                }
                
                // Fallback to old structure if inventories not found or stock is 0
                if ($cjStock === 0) {
                    $fallbackFields = ['stock', 'variantStock', 'inventoryNum', 'cjInventory', 'totalInventory'];
                    $stockDebugInfo['fallback_fields_checked'] = $fallbackFields;
                    
                    foreach ($fallbackFields as $field) {
                        if (isset($variantData[$field])) {
                            $value = (int) $variantData[$field];
                            $stockDebugInfo['fallback_' . $field] = $value;
                            
                            if ($value > 0) {
                                $cjStock = $value;
                                $stockDebugInfo['fallback_used'] = $field;
                                $stockDebugInfo['fallback_value'] = $cjStock;
                                break;
                            }
                        } else {
                            $stockDebugInfo['fallback_' . $field] = 'missing';
                        }
                    }
                    
                    // SPECIAL FIX: Check if inventoryNum exists but was missed
                    if ($cjStock === 0 && in_array('inventoryNum', array_keys($variantData))) {
                        $inventoryNum = (int) ($variantData['inventoryNum'] ?? 0);
                        $stockDebugInfo['special_inventoryNum_check'] = $inventoryNum;
                        
                        if ($inventoryNum > 0) {
                            $cjStock = $inventoryNum;
                            $stockDebugInfo['fallback_used'] = 'inventoryNum_special';
                            $stockDebugInfo['fallback_value'] = $cjStock;
                        }
                    }
                }
                
                $stockOnHand = $this->calculateStockOnHand($cjStock);
                
                // ALWAYS log detailed info for debugging the zero stock issue
                if ($cjStock === 0) {
                    Log::warning('CJ variant stock zero - INVESTIGATE THIS', $stockDebugInfo);
                } else {
                    Log::info('CJ variant stock extracted successfully', array_merge($stockDebugInfo, [
                        'cj_stock' => $cjStock,
                        'stock_on_hand' => $stockOnHand,
                    ]));
                }
                $price = $this->resolveVariantPrice($variantData, $existingVariant ?? new ProductVariant(), $product);
                $title = $this->resolveVariantTitle($variantData, $existingVariant ?? new ProductVariant(), $vid);

                // Real-time stock management: Check for actual changes
                $stockChanged = false;
                $previousStock = $existingVariant?->cj_stock ?? 0;
                $previousStockOnHand = $existingVariant?->stock_on_hand ?? 0;
                
                if ($previousStock !== $cjStock || $previousStockOnHand !== $stockOnHand) {
                    $stockChanged = true;
                    
                    // Log stock changes for real-time monitoring
                    Log::info('CJ variant stock changed', [
                        'product_id' => $product->id,
                        'cj_vid' => $vid,
                        'previous_cj_stock' => $previousStock,
                        'new_cj_stock' => $cjStock,
                        'previous_stock_on_hand' => $previousStockOnHand,
                        'new_stock_on_hand' => $stockOnHand,
                        'change_amount' => $cjStock - $previousStock,
                    ]);
                    
                    // Cache stock for real-time access
                    Cache::put("variant_stock_{$vid}", [
                        'cj_stock' => $cjStock,
                        'stock_on_hand' => $stockOnHand,
                        'updated_at' => now(),
                    ], 300); // 5 minutes cache
                    
                    // Dispatch stock change event for real-time systems
                    Event::dispatch('variant.stock.changed', [
                        'variant' => $existingVariant,
                        'product_id' => $product->id,
                        'cj_vid' => $vid,
                        'previous_stock' => $previousStock,
                        'new_stock' => $cjStock,
                        'previous_stock_on_hand' => $previousStockOnHand,
                        'new_stock_on_hand' => $stockOnHand,
                        'timestamp' => now(),
                    ]);
                }

                // Use updateOrCreate to avoid column order issues
                $variant = ProductVariant::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'cj_vid' => $vid,
                    ],
                    [
                        'sku' => $sku,
                        'cj_stock' => $cjStock,
                        'stock_on_hand' => $stockOnHand,
                        'cj_stock_synced_at' => now(),
                        'price' => $price,
                        'title' => $title,
                    ]
                );

                // Enhanced logging for real-time monitoring
                if ($stockChanged) {
                    Log::info('CJ variant synced with stock changes', [
                        'product_id' => $product->id,
                        'cj_vid' => $vid,
                        'stock' => $variant->cj_stock,
                        'stock_on_hand' => $variant->stock_on_hand,
                        'changed' => true,
                    ]);
                } else {
                    Log::debug('CJ variant synced (no stock change)', [
                        'product_id' => $product->id,
                        'cj_vid' => $vid,
                        'stock' => $variant->cj_stock,
                        'stock_on_hand' => $variant->stock_on_hand,
                        'changed' => false,
                    ]);
                }
            }

            // Update product sync timestamp
            $product->cj_removed_from_shelves_at = null;
            $product->cj_removed_reason = null;
            $product->cj_synced_at = now();
            $product->save();

        } catch (ApiException $e) {
            if ($this->isRemovedFromShelves($e)) {
                $this->markProductRemoved($e->getMessage());
                return;
            }

            Log::warning('CJ variant sync failed', [
                'cj_pid' => $this->cjPid,
                'error' => $e->getMessage(),
                'status' => $e->status,
            ]);

            // If rate-limited by CJ, requeue with exponential backoff
            if ($e->status === 429) {
                $attempt = max(1, $this->attempts());
                $delay = min(60 * (2 ** ($attempt - 1)), 3600); // cap at 1 hour
                Log::info('CJ rate limit hit; releasing job back to queue', [
                    'cj_pid' => $this->cjPid,
                    'delay' => $delay,
                ]);
                $this->release($delay);
                return;
            }

            // For other errors, fail the job
            $this->fail($e);
        }
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function extractVariants(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        if (array_is_list($data)) {
            return $data;
        }

        $candidates = [
            $data['variants'] ?? null,
            $data['list'] ?? null,
            $data['data'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            if (array_is_list($candidate)) {
                return $candidate;
            }

            if (isset($candidate['variants']) && is_array($candidate['variants'])) {
                return $candidate['variants'];
            }

            if (isset($candidate['list']) && is_array($candidate['list'])) {
                return $candidate['list'];
            }
        }

        return null;
    }

    private function isRemovedFromShelves(ApiException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'removed from shelves')
            || str_contains($message, 'off shelf')
            || str_contains($message, 'offline')
            || in_array($e->codeString, ['PRODUCT_OFF_SHELF', '404'], true);
    }

    private function markProductRemoved(?string $reason = null): void
    {
        Product::query()
            ->where('cj_pid', $this->cjPid)
            ->update([
                'status' => 'draft',
                'is_active' => false,
                'cj_sync_enabled' => false,
                'cj_synced_at' => now(),
                'cj_removed_from_shelves_at' => now(),
                'cj_removed_reason' => $reason !== null ? substr($reason, 0, 500) : 'Removed from shelves',
            ]);

        Log::warning('CJ product marked as removed during variants sync', [
            'cj_pid' => $this->cjPid,
            'reason' => $reason,
        ]);
    }

    /**
     * @param array<string, mixed> $variantData
     */
    private function resolveVariantTitle(array $variantData, ProductVariant $variant, string $vid): string
    {
        $candidates = [
            $variantData['variantName'] ?? null,
            $variantData['variantNameEn'] ?? null,
            $variantData['variantKey'] ?? null,
            $variantData['variantSku'] ?? null,
            $variant->title ?? null,
            'Variant ' . $vid,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '' && strtolower($value) !== 'null') {
                return $value;
            }
        }

        return 'Variant';
    }

    /**
     * @param array<string, mixed> $variantData
     */
    private function resolveVariantPrice(array $variantData, ProductVariant $variant, Product $product): float
    {
        // Try variant-specific prices first
        $candidate = $variantData['variantSellPrice']
            ?? $variantData['variantSugSellPrice']
            ?? $variantData['variantPrice'];

        // If variant price is not available, calculate from variant cost
        if (!is_numeric($candidate)) {
            $variantCost = $variantData['variantSellPrice'] ?? $variant->cost_price ?? 0;
            if (is_numeric($variantCost) && $variantCost > 0) {
                // Calculate variant price based on its own cost, not the potentially corrupted product price
                $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
                $candidate = $pricing->minSellingPrice((float) $variantCost);
            } else {
                $candidate = 0.0;
            }
        }

        // Final validation to prevent corruption
        if (!is_numeric($candidate) || $candidate < 0) {
            $candidate = 0.0;
        }

        // Additional corruption prevention
        $variantCost = $variant->cost_price ?? 0;
        if ($variantCost > 0 && $candidate > ($variantCost * 100)) { // >100x markup is corruption
            Log::warning('Excessive variant price detected in resolveVariantPrice, using minimum', [
                'cj_pid' => $product->cj_pid,
                'cj_vid' => $variant->cj_vid,
                'variant_cost' => $variantCost,
                'candidate_price' => $candidate
            ]);
            $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
            $candidate = $pricing->minSellingPrice((float) $variantCost);
        }

        return (float) $candidate;
    }

    /**
     * Calculate stock_on_hand with configurable percentage
     */
    private function calculateStockOnHand(int $totalStock): int
    {
        if ($totalStock <= 0) {
            return 0;
        }

        // Get configurable stock percentage (default 75% instead of 50%)
        $percentage = (float) config('services.cj.stock_percentage', 75.0);
        
        // Ensure percentage is between 10% and 100%
        $percentage = max(10.0, min(100.0, $percentage));
        
        $stockOnHand = (int) ($totalStock * ($percentage / 100.0));
        
        Log::debug('Stock calculation in SyncCjVariantsJob', [
            'total_stock' => $totalStock,
            'percentage' => $percentage,
            'stock_on_hand' => $stockOnHand,
        ]);

        return $stockOnHand;
    }

    /**
     * Debug method to investigate specific variant stock issues
     * Call this method directly to debug a problematic variant
     */
    public function debugVariantStock(string $cjPid, string $vid): void
    {
        try {
            $client = new CJDropshippingClient();
            
            Log::info("=== DEBUGGING VARIANT STOCK ===");
            Log::info("CJ PID: {$cjPid}");
            Log::info("VID: {$vid}");
            Log::info("Default Warehouse: " . env('CJ_DEFAULT_WAREHOUSE', 'CN'));
            
            // Get variants from CJ API
            $resp = $client->getVariantsByPid($cjPid);
            $variants = $this->extractVariants($resp->data ?? null);
            
            if (!$variants) {
                Log::error("No variants found for CJ PID: {$cjPid}");
                return;
            }
            
            // Find the specific variant
            $targetVariant = null;
            foreach ($variants as $variantData) {
                if ((string) ($variantData['vid'] ?? '') === $vid) {
                    $targetVariant = $variantData;
                    break;
                }
            }
            
            if (!$targetVariant) {
                Log::error("Variant {$vid} not found in CJ response for PID {$cjPid}");
                Log::info("Available VIDs: " . implode(', ', array_map(fn($v) => $v['vid'] ?? 'missing', $variants)));
                return;
            }
            
            // Detailed analysis of the target variant
            Log::info("=== TARGET VARIANT FOUND ===");
            Log::info("Full variant data keys: " . implode(', ', array_keys($targetVariant)));
            
            // Check inventories structure
            if (isset($targetVariant['inventories'])) {
                Log::info("Inventories structure found:");
                Log::info("Type: " . gettype($targetVariant['inventories']));
                
                if (is_array($targetVariant['inventories'])) {
                    foreach ($targetVariant['inventories'] as $index => $inventory) {
                        Log::info("Inventory {$index}:");
                        Log::info("  Country: " . ($inventory['countryCode'] ?? 'missing'));
                        Log::info("  Total Inventory: " . ($inventory['totalInventory'] ?? 'missing'));
                        Log::info("  CJ Inventory: " . ($inventory['cjInventory'] ?? 'missing'));
                        Log::info("  Factory Inventory: " . ($inventory['factoryInventory'] ?? 'missing'));
                        Log::info("  Is Default Warehouse: " . (($inventory['countryCode'] ?? null) === env('CJ_DEFAULT_WAREHOUSE', 'CN') ? 'YES' : 'NO'));
                    }
                }
            } else {
                Log::warning("No inventories structure found");
            }
            
            // Check fallback fields
            $fallbackFields = ['stock', 'variantStock', 'inventoryNum'];
            Log::info("Fallback field check:");
            foreach ($fallbackFields as $field) {
                $value = $targetVariant[$field] ?? 'missing';
                Log::info("  {$field}: {$value}");
            }
            
            // Test stock extraction
            $cjStock = 0;
            if (isset($targetVariant['inventories']) && is_array($targetVariant['inventories'])) {
                foreach ($targetVariant['inventories'] as $inventory) {
                    if (isset($inventory['countryCode']) && $inventory['countryCode'] === env('CJ_DEFAULT_WAREHOUSE', 'CN')) {
                        $cjStock = (int) ($inventory['totalInventory'] ?? $inventory['cjInventory'] ?? 0);
                        Log::info("Found warehouse stock: {$cjStock}");
                        break;
                    }
                }
            }
            
            if ($cjStock === 0) {
                foreach ($fallbackFields as $field) {
                    if (isset($targetVariant[$field])) {
                        $cjStock = (int) $targetVariant[$field];
                        Log::info("Used fallback {$field}: {$cjStock}");
                        break;
                    }
                }
            }
            
            Log::info("=== FINAL RESULT ===");
            Log::info("CJ Stock: {$cjStock}");
            Log::info("Stock on Hand: " . $this->calculateStockOnHand($cjStock));
            
        } catch (\Exception $e) {
            Log::error("Debug failed: " . $e->getMessage(), [
                'cj_pid' => $cjPid,
                'vid' => $vid,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
