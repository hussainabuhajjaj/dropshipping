<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Models\SiteSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CjSyncCatalog extends Command
{
    protected $signature = 'cj:sync-catalog';
    protected $description = 'Pull CJ categories and product summaries respecting the platform rate limits.';

    public function handle(): int
    {
        $client = app(CJDropshippingClient::class);
        $this->info('Fetching CJ categories…');

        try {
            $categories = $client->listCategories();
            $products = $client->listProducts(['pageSize' => 20, 'pageNum' => 1]);
        } catch (\Throwable $e) {
            $this->error('CJ sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        SiteSetting::query()->updateOrCreate([], [
            'cj_last_sync_at' => now(),
            'cj_last_sync_summary' => trans('cj.sync_summary', [
                'categories' => count($categories->data ?? []),
                'products' => $products->data['totalRecords'] ?? 0,
            ]),
        ]);

        Cache::put('cj.sync.categories', $categories->data, now()->addMinutes(5));
        Cache::put('cj.sync.products.summary', $products->data, now()->addMinutes(5));

        $this->info('CJ catalog sync completed.');
        return self::SUCCESS;
    }
}
