<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Services\ProductActivationValidator;
use App\Domain\Products\Services\PricingService;
use App\Models\Product;
use Illuminate\Console\Command;

class CjActivateDraftProducts extends Command
{
    protected $signature = 'cj:activate-draft-products 
                            {--margin=60 : Margin percentage to apply}
                            {--dry-run : Preview without making changes}';

    protected $description = 'Fix and activate draft CJ products by applying margin and re-importing if needed';

    public function handle(): int
    {
        if (PricingService::usesNewEngine()) {
            $this->warn('pricing.use_new_engine is enabled. This legacy activation command is blocked to avoid mixing pricing engines.');

            return self::INVALID;
        }

        $margin = (float) $this->option('margin');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Finding draft CJ products...");

        $products = Product::whereNotNull('cj_pid')
            ->where('status', 'draft')
            ->where('is_active', false)
            ->get();

        if ($products->isEmpty()) {
            $this->info('✅ No draft products found!');
            return self::SUCCESS;
        }

        $this->warn("Found {$products->count()} draft products");

        $validator = app(ProductActivationValidator::class);
        $activated = 0;
        $needsReimport = [];
        $needsCategory = [];

        foreach ($products as $product) {
            $errors = $validator->errorsForActivation($product);

            // Check if product can be activated as-is
            if (empty($errors)) {
                if (!$dryRun) {
                    $product->update(['status' => 'active', 'is_active' => true]);
                    $activated++;
                    $this->line("✅ Activated: {$product->name}");
                } else {
                    $this->line("✅ Can activate: {$product->name}");
                    $activated++;
                }
                continue;
            }

            // Check if product has zero cost (needs re-import)
            if ($product->cost_price <= 0) {
                $needsReimport[] = $product->cj_pid;
                $this->line("🔄 Needs re-import: {$product->name} (zero cost)");
                continue;
            }

            // Check if missing category
            if (!$product->category_id) {
                $needsCategory[] = $product->id;
                $this->line("📁 Needs category: {$product->name}");
            }

            // Apply margin and try to activate
            if (!$dryRun) {
                $marginFactor = 1 + ($margin / 100);
                $product->selling_price = round($product->cost_price * $marginFactor, 2);
                $product->save();

                // Update variants
                foreach ($product->variants as $variant) {
                    if ($variant->cost_price > 0) {
                        $variant->price = round($variant->cost_price * $marginFactor, 2);
                        $variant->save();
                    }
                }

                // Re-check validation
                $errors = $validator->errorsForActivation($product);
                if (empty($errors)) {
                    $product->update(['status' => 'active', 'is_active' => true]);
                    $activated++;
                    $this->line("✅ Fixed & activated: {$product->name} ({$margin}% margin)");
                } else {
                    $this->line("⚠️  Fixed pricing but still has errors: {$product->name}");
                    foreach ($errors as $error) {
                        $this->line("   - {$error}");
                    }
                }
            } else {
                $this->line("🔧 Would fix: {$product->name} (apply {$margin}% margin)");
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->line("✅ Activated: {$activated}");
        
        if (!empty($needsReimport)) {
            $this->line("🔄 Need re-import: " . count($needsReimport));
            $this->line("   Run: php artisan cj:fix-zero-prices --limit=" . count($needsReimport));
        }

        if (!empty($needsCategory)) {
            $this->line("📁 Need category assignment: " . count($needsCategory));
            $this->line("   Assign categories manually in admin panel");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run - no changes made. Run without --dry-run to apply fixes.');
        }

        return self::SUCCESS;
    }
}
