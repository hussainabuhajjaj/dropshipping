<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AnalyzeCjSkuPatterns extends Command
{
    protected $signature = 'cj:analyze-sku-patterns
        {--export : Export results to file}
        {--fix-conflicts : Fix SKU conflicts with better pattern}';

    protected $description = 'Analyze CJ SKU patterns and handle conflicts intelligently';

    public function handle(): int
    {
        $this->info('🏷️  CJ SKU Pattern Analysis');
        $this->info('==========================');

        $export = $this->option('export');
        $fixConflicts = $this->option('fix-conflicts');

        // Analyze current SKU patterns
        $this->analyzeSkuPatterns();

        // Check for conflicts
        $conflicts = $this->findSkuConflicts();
        
        if ($conflicts->isNotEmpty()) {
            $this->handleSkuConflicts($conflicts, $fixConflicts);
        }

        // Export if requested
        if ($export) {
            $this->exportAnalysis($conflicts);
        }

        return self::SUCCESS;
    }

    private function analyzeSkuPatterns(): void
    {
        $this->info('📊 Analyzing SKU patterns...');

        // Get all variant SKUs
        $skus = ProductVariant::whereNotNull('sku')
            ->where('sku', '!=', '')
            ->pluck('sku');

        $patterns = [
            'VAR-X-YYYYY' => 0,  // Your pattern: VAR-7-21998
            'CJ-VID' => 0,       // CJ-2602050951151620600
            'CJ-VID-ProductID' => 0, // CJ-VID-123
            'Other' => 0,
        ];

        foreach ($skus as $sku) {
            if (preg_match('/^VAR-\d+-\d+$/', $sku)) {
                $patterns['VAR-X-YYYYY']++;
            } elseif (preg_match('/^CJ-\d+$/', $sku)) {
                $patterns['CJ-VID']++;
            } elseif (preg_match('/^CJ-\d+-\d+$/', $sku)) {
                $patterns['CJ-VID-ProductID']++;
            } else {
                $patterns['Other']++;
            }
        }

        $this->table(['Pattern', 'Count', 'Example'], [
            ['VAR-X-YYYYY', $patterns['VAR-X-YYYYY'], 'VAR-7-21998'],
            ['CJ-VID', $patterns['CJ-VID'], 'CJ-2602050951151620600'],
            ['CJ-VID-ProductID', $patterns['CJ-VID-ProductID'], 'CJ-VID-123'],
            ['Other', $patterns['Other'], 'Various'],
        ]);

        $totalVariants = $skus->count();
        $this->info("Total variants with SKUs: {$totalVariants}");
    }

    private function findSkuConflicts()
    {
        $this->info("\n🔍 Finding SKU conflicts...");

        // Find SKUs that appear multiple times within the same product
        $conflicts = ProductVariant::select('sku', 'product_id')
            ->selectRaw('COUNT(*) as count')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereHas('product', function ($q) {
                $q->whereNotNull('cj_pid');
            })
            ->groupBy('sku', 'product_id')
            ->having('count', '>', 1)
            ->with(['product' => function ($q) {
                $q->select('id', 'cj_pid', 'name');
            }])
            ->get();

        if ($conflicts->isNotEmpty()) {
            $this->error("Found {$conflicts->count()} SKU conflicts within products!");
            
            foreach ($conflicts->take(5) as $conflict) {
                $this->line("  • SKU '{$conflict->sku}' appears {$conflict->count} times in product {$conflict->product->cj_pid}");
            }
        } else {
            $this->info("✅ No SKU conflicts found!");
        }

        return $conflicts;
    }

    private function handleSkuConflicts($conflicts, bool $fixConflicts): void
    {
        if (!$fixConflicts) {
            $this->info("\n💡 To fix conflicts, run with --fix-conflicts option");
            return;
        }

        $this->info("\n🔧 Fixing SKU conflicts...");

        $fixed = 0;
        foreach ($conflicts as $conflict) {
            // Get all variants with this SKU in this product
            $variants = ProductVariant::where('product_id', $conflict->product_id)
                ->where('sku', $conflict->sku)
                ->orderBy('cj_vid')
                ->get();

            $productCjPid = $conflict->product->cj_pid;
            $baseSku = $conflict->sku;
            
            // Extract pattern from base SKU
            if (preg_match('/^(VAR-\d+)-(\d+)$/', $baseSku, $matches)) {
                $prefix = $matches[1]; // VAR-7
                $baseId = $matches[2]; // 21998
            } else {
                $prefix = 'VAR-' . substr($productCjPid, -4);
                $baseId = substr($baseSku, -4);
            }

            foreach ($variants as $index => $variant) {
                if ($index === 0) {
                    // Keep first variant as-is
                    continue;
                }

                // Generate new SKU maintaining pattern
                $newSku = $prefix . '-' . $baseId . '-' . ($index + 1);

                // Update variant
                $variant->update(['sku' => $newSku]);
                
                $this->line("  Updated: {$baseSku} → {$newSku} (VID: {$variant->cj_vid})");
                $fixed++;
            }
        }

        $this->info("✅ Fixed {$fixed} SKU conflicts");
    }

    private function exportAnalysis($conflicts): void
    {
        $filename = 'cj-sku-analysis-' . date('Y-m-d-H-i-s') . '.json';
        $filepath = storage_path("logs/{$filename}");
        
        $data = [
            'timestamp' => now()->toISOString(),
            'conflicts' => $conflicts->toArray(),
            'total_conflicts' => $conflicts->count(),
        ];

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
        $this->info("Analysis exported to: {$filepath}");
    }
}
