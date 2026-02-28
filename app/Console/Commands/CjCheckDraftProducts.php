<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Services\ProductActivationValidator;
use App\Models\Product;
use Illuminate\Console\Command;

class CjCheckDraftProducts extends Command
{
    protected $signature = 'cj:check-draft-products {--limit=10}';

    protected $description = 'Check validation errors for draft CJ products';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $products = Product::whereNotNull('cj_pid')
            ->where('status', 'draft')
            ->where('is_active', false)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($products->isEmpty()) {
            $this->info('✅ No draft products found!');
            return self::SUCCESS;
        }

        $this->warn("Found {$products->count()} draft products:");
        $this->newLine();

        $validator = app(ProductActivationValidator::class);

        foreach ($products as $product) {
            $errors = $validator->errorsForActivation($product);

            $this->line("📦 <fg=cyan>{$product->name}</>");
            $this->line("   PID: {$product->cj_pid}");
            $this->line("   Cost: $" . ($product->cost_price ?? 'NULL'));
            $this->line("   Selling: $" . ($product->selling_price ?? 'NULL'));
            $this->line("   Category: " . ($product->category_id ?? 'NULL'));
            $this->line("   Images: {$product->images()->count()}");
            $this->line("   Variants: {$product->variants()->count()}");

            if (empty($errors)) {
                $this->line("   <fg=green>✅ No validation errors - can be activated!</>");
            } else {
                $this->line("   <fg=red>❌ Validation Errors:</>");
                foreach ($errors as $error) {
                    $this->line("      - {$error}");
                }
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
