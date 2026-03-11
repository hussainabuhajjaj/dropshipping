<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Search\TypesenseSearchService;
use Illuminate\Console\Command;

class TypesenseSyncProducts extends Command
{
    protected $signature = 'typesense:sync-products {--fresh : Drop and recreate collection before syncing}';

    protected $description = 'Sync all active products into Typesense';

    public function handle(TypesenseSearchService $service): int
    {
        if (! config('typesense.enabled')) {
            $this->warn('Typesense is disabled (SEARCH_DRIVER != typesense).');
            return self::SUCCESS;
        }

        if ($this->option('fresh')) {
            $service->recreateCollection();
            $this->info('Collection recreated.');
        }

        $this->info('Syncing products...');

        Product::query()
            ->where('is_active', true)
            ->with(['category', 'variants'])
            ->chunk(500, function ($products) use ($service) {
                foreach ($products as $product) {
                    $service->upsert($product);
                }
            });

        $this->info('Done.');
        return self::SUCCESS;
    }
}
