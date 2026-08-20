<?php

namespace App\Console\Commands;

use App\Models\StorefrontCampaign;
use App\Domain\Products\Models\Product;
use Illuminate\Console\Command;

class CampaignsAttachProducts extends Command
{
    protected $signature = 'campaigns:attach-products
        {--campaign= : Campaign ID to attach products for}';

    protected $description = 'Attach newly imported active products to campaign auto-collections';

    public function handle(): int
    {
        $campaignId = $this->option('campaign');
        $attached = 0;

        $campaigns = StorefrontCampaign::query()
            ->where('is_active', true)
            ->whereHas('autoCollection')
            ->when($campaignId, fn ($q) => $q->where('id', (int) $campaignId))
            ->get();

        if ($campaigns->isEmpty()) {
            $this->warn('No campaigns with auto-collections found.');
            return 0;
        }

        foreach ($campaigns as $campaign) {
            $collection = $campaign->autoCollection;

            $collectionProductIds = $collection->products()->pluck('products.id')->all();
            $sourcedAt = $campaign->productQuery?->sourced_at;

            $newProducts = Product::where('is_active', true)
                ->whereNotNull('cj_pid')
                ->when($sourcedAt, fn ($query) => $query->where('cj_imported_at', '>=', $sourcedAt))
                ->whereNotIn('id', $collectionProductIds)
                ->limit((int) ($campaign->productQuery?->max_products ?: 50))
                ->get();

            if ($newProducts->isEmpty()) {
                $this->line("  Campaign #{$campaign->id}: no new products to attach");
                continue;
            }

            $newIds = $newProducts->pluck('id')->all();
            $collection->products()->syncWithoutDetaching(
                collect($newIds)->mapWithKeys(fn ($id, $i) => [$id => ['position' => 999 + $i]])->all()
            );

            $this->line("  Campaign #{$campaign->id}: attached {$newProducts->count()} new product(s)");
            $attached += $newProducts->count();
        }

        $this->info("Attached {$attached} product(s) total.");
        return 0;
    }
}
