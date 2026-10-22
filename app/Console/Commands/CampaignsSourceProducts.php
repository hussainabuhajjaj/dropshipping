<?php

namespace App\Console\Commands;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Jobs\ImportCjProductPipelineChunkJob;
use App\Models\CampaignProductQuery;
use App\Models\StorefrontCampaign;
use App\Models\StorefrontCollection;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CampaignsSourceProducts extends Command
{
    protected $signature = 'campaigns:source-products
        {--campaign= : Source for a specific campaign ID}
        {--dry-run : Log what would happen without importing}';

    protected $description = 'Source products from CJ for upcoming campaigns';

    public function handle(CJDropshippingClient $cjClient): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $campaignId = $this->option('campaign');
        $sourced = 0;

        $campaigns = $this->getCampaignsForSourcing($campaignId);

        if ($campaigns->isEmpty()) {
            $this->info('No campaigns need product sourcing at this time.');
            return 0;
        }

        foreach ($campaigns as $campaign) {
            $this->line("Processing campaign #{$campaign->id}: {$campaign->name}");

            $query = $campaign->productQuery;
            if (! $query) {
                $this->warn("  No product query configured for campaign #{$campaign->id}");
                continue;
            }

            if ($query->status === 'completed') {
                $this->line("  Already sourced at {$query->sourced_at}");
                continue;
            }

            $pids = $this->searchProducts($cjClient, $query);

            if (empty($pids)) {
                $query->markAsFailed('No products found from CJ search');
                $this->warn('  No products found from CJ search');
                continue;
            }

            $this->line("  Found " . count($pids) . " products from CJ");

            if ($dryRun) {
                $this->line("  [DRY-RUN] Would import " . count($pids) . " products");
                $sourced++;
                continue;
            }

            $trackingKey = $this->importProductsAsync($pids, $query);
            $this->line("  Dispatched " . count($pids) . " products for import (tracking: {$trackingKey})");

            $sourcingConfig = $campaign->sourcingConfig();
            $query->markAsSourced();

            if ($sourcingConfig['auto_create_collection']) {
                $this->linkExistingActiveProducts($campaign, $pids);
            }

            $sourced++;
        }

        $this->info("Sourced products for {$sourced} campaign(s).");

        if ($dryRun) {
            $this->warn('Dry-run — no products were actually imported.');
        }

        return 0;
    }

    private function getCampaignsForSourcing(?string $campaignId): \Illuminate\Support\Collection
    {
        $query = StorefrontCampaign::query()
            ->where('is_active', true)
            ->whereIn('status', ['approved', 'scheduled', 'active'])
            ->whereHas('productQuery', fn ($q) => $q->where('status', '!=', 'completed'));

        if ($campaignId) {
            $query->where('id', (int) $campaignId);
        } else {
            $query->where(function ($q) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', now()->addDays(14));
            });
        }

        return $query->get();
    }

    private function searchProducts(CJDropshippingClient $client, CampaignProductQuery $query): array
    {
        $allPids = [];
        $keywords = $query->keywords ? explode(',', $query->keywords) : [''];
        $perKeyword = max(1, (int) ceil($query->max_products / max(1, count($keywords))));
        $categoryIds = collect(explode(',', (string) $query->cj_category_id))
            ->map(fn ($categoryId) => trim($categoryId))
            ->filter()
            ->values()
            ->all();

        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (empty($keyword)) {
                continue;
            }

            foreach ($categoryIds ?: [null] as $categoryId) {
                try {
                    $filters = [
                        'pageNum' => 1,
                        'pageSize' => min($perKeyword, 50),
                        'productName' => $keyword,
                        'sort' => $query->sort_by === 'sales' ? 'sales' : ($query->sort_by === 'newest' ? 'newest' : null),
                    ];

                    if ($categoryId) {
                        $filters['categoryId'] = $categoryId;
                    }

                    $response = $client->listProductsV2($filters);

                    $content = $response->data['content'] ?? [];
                    $products = [];
                    if (is_array($content) && isset($content[0]['productList'])) {
                        $products = $content[0]['productList'];
                    }

                    foreach ($products as $product) {
                        $pid = (string) ($product['id'] ?? '');
                        if ($pid !== '' && ! in_array($pid, $allPids, true)) {
                            $allPids[] = $pid;
                        }
                    }

                    $categoryLabel = $categoryId ? " (category {$categoryId})" : '';
                    $this->line("  Keyword '{$keyword}'{$categoryLabel}: found " . count($products) . " products");
                } catch (\Throwable $e) {
                    $this->warn("  Search failed for keyword '{$keyword}': {$e->getMessage()}");
                    Log::warning('[CampaignSourcing] CJ search failed', [
                        'keyword' => $keyword,
                        'category_id' => $categoryId,
                        'error' => $e->getMessage(),
                    ]);
                }

                if (count($allPids) >= $query->max_products) {
                    $allPids = array_slice($allPids, 0, $query->max_products);
                    break 2;
                }
            }
        }

        return $allPids;
    }

    private function importProductsAsync(array $pids, CampaignProductQuery $query): string
    {
        // Keep campaign imports small because enrichment and media sync are network-heavy.
        $chunks = array_chunk($pids, 5);
        $trackingKey = 'campaign-sourcing-' . $query->storefront_campaign_id . '-' . Str::random(8);

        foreach ($chunks as $index => $chunk) {
            try {
                ImportCjProductPipelineChunkJob::dispatch($chunk, [
                    'tracking_key' => $trackingKey,
                    'margin_percent' => $query->margin_percent,
                    'force_activate' => $query->auto_activate,
                    'enrich' => true,
                    'skip_translations' => false,
                    'skip_seo' => false,
                    'chunk_index' => $index,
                ])->onQueue('cj-import');
            } catch (\Throwable $e) {
                $this->warn("  Chunk {$index} dispatch failed: {$e->getMessage()}");
                Log::error('[CampaignSourcing] Import chunk dispatch failed', [
                    'chunk_index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $trackingKey;
    }

    private function linkExistingActiveProducts(StorefrontCampaign $campaign, array $pids): void
    {
        $productIds = Product::whereIn('cj_pid', $pids)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if (empty($productIds)) {
            $this->line('  No already-active products to add to collection yet (imports are queued)');
            return;
        }

        $collection = $campaign->autoCollection;

        if (! $collection) {
            $collection = StorefrontCollection::create([
                'title' => $campaign->name,
                'slug' => Str::slug($campaign->name) . '-' . $campaign->id,
                'type' => 'campaign',
                'description' => $campaign->hero_subtitle ?? "{$campaign->name} collection",
                'is_active' => true,
                'selection_mode' => 'manual',
                'display_order' => $campaign->priority ?? 0,
                'is_campaign_auto_created' => true,
                'campaign_id' => $campaign->id,
                'product_limit' => count($productIds),
            ]);

            $this->line("  Created collection #{$collection->id}: {$collection->title}");
        }

        $collection->products()->syncWithoutDetaching(
            collect($productIds)->mapWithKeys(fn ($id, $i) => [$id => ['position' => $i + 1]])->all()
        );

        $this->line("  Added {$collection->products()->count()} products to collection #{$collection->id}");

        $campaign->refresh();
        $existingIds = $campaign->collectionIds();
        if (! in_array($collection->id, $existingIds, true)) {
            $campaign->update([
                'collection_ids' => array_merge($existingIds, [$collection->id]),
            ]);
        }

        $this->line("  (Run 'campaigns:attach-products --campaign={$campaign->id}' later to attach newly imported products)");
    }
}
