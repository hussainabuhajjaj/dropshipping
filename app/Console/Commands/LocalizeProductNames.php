<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Services\AI\ProductTranslationService;
use Illuminate\Console\Command;

class LocalizeProductNames extends Command
{
    protected $signature = 'products:localize-names';

    protected $description = 'Set manually localised French names for the top 20 products (Fix #7 CRO)';

    public function handle(ProductTranslationService $service): int
    {
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get();

        if ($products->isEmpty()) {
            $this->info('No active products found.');

            return self::SUCCESS;
        }

        $this->info("Translating {$products->count()} product(s) to French...");

        $rows = [];

        foreach ($products as $product) {
            $service->translate($product, ['fr'], 'en', true);

            $translation = $product->fresh()->translationForLocale('fr');

            $rows[] = [
                $product->id,
                $product->name,
                $translation?->name ?? 'N/A',
            ];
        }

        $this->table(['ID', 'Original Name', 'French Name'], $rows);

        $this->info('Done.');

        return self::SUCCESS;
    }
}
