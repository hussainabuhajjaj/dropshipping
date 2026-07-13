<?php

namespace App\Console\Commands;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Jobs\ImportCjProductPipelineChunkJob;
use App\Domain\Products\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportCjTrendingProducts extends Command
{
    protected $signature = 'cj:import-trending
        {--dry-run : Log what would happen without importing}';

    protected $description = 'Import daily trending products from CJ across women, men, and kids categories';

    private const CATEGORIES = [
        'trending' => [
            'keywords' => ['hot sale', 'trending', 'bestseller', 'popular'],
            'cj_category_id' => null,
            'target' => 10,
        ],
        'women' => [
            'keywords' => ['women dress', 'women top', 'women fashion'],
            'cj_category_id' => '2FE8A083-5E7B-4179-896D-561EA116F730',
            'target' => 7,
        ],
        'men' => [
            'keywords' => ['men shirt', 'men fashion', 'men clothes'],
            'cj_category_id' => 'B8302697-CF47-4211-9BD0-DFE8995AEB30',
            'target' => 7,
        ],
        'kids' => [
            'keywords' => ['kids clothes', 'children fashion', 'baby clothes'],
            'cj_category_id' => 'A50A92FA-BCB3-4716-9BD9-BEC629BEE735',
            'target' => 6,
        ],
    ];

    public function handle(CJDropshippingClient $cjClient): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $allPids = [];

        foreach (self::CATEGORIES as $group => $config) {
            $this->line("Searching {$group} products...");
            $pids = $this->searchCategory($cjClient, $config);
            $allPids[$group] = $pids;
            $this->line("  Found " . count($pids) . " unique PIDs for {$group}");
        }

        $flatPids = collect($allPids)->flatten()->unique()->values()->all();
        $this->line("\nTotal unique PIDs found: " . count($flatPids));

        $existingPids = Product::whereIn('cj_pid', $flatPids)
            ->pluck('cj_pid')
            ->map(fn ($v) => (string) $v)
            ->all();

        $newPids = array_values(array_diff($flatPids, $existingPids));
        $this->line("Already in DB: " . count($existingPids) . ", new to import: " . count($newPids));

        if (empty($newPids)) {
            $this->info("No new products to import.");
            return 0;
        }

        if ($dryRun) {
            $this->warn("Dry-run — would queue " . count($newPids) . " products for import.");
            return 0;
        }

        $trackingKey = 'cj-trending-' . now()->format('Ymd') . '-' . Str::random(6);
        $chunks = array_chunk($newPids, 10);
        $dispatched = 0;

        foreach ($chunks as $index => $chunk) {
            try {
                ImportCjProductPipelineChunkJob::dispatch($chunk, [
                    'tracking_key' => $trackingKey,
                    'margin_percent' => (float) config('services.cj.import_margin', 60),
                    'force_activate' => true,
                    'enrich' => true,
                    'skip_translations' => false,
                    'skip_seo' => false,
                    'chunk_index' => $index,
                ])->onQueue('cj-daily');

                $dispatched += count($chunk);
            } catch (\Throwable $e) {
                Log::error('[CJ Trending] Dispatch failed', [
                    'error' => $e->getMessage(),
                    'chunk_index' => $index,
                ]);
            }
        }

        $this->info("Dispatched {$dispatched} products for import (tracking: {$trackingKey}).");
        $this->line("Run: php artisan queue:work redis --queue=cj-daily --once --timeout=600");

        return 0;
    }

    private function searchCategory(CJDropshippingClient $client, array $config): array
    {
        $allPids = [];
        $keywords = $config['keywords'] ?? [''];
        $perKeyword = max(5, (int) ceil($config['target'] / max(1, count($keywords))));

        foreach ($keywords as $keyword) {
            if (empty(trim($keyword))) {
                continue;
            }

            try {
                $filters = [
                    'pageNum' => 1,
                    'pageSize' => min($perKeyword * 2, 50),
                    'productName' => trim($keyword),
                    'sort' => 'sales',
                ];

                if ($config['cj_category_id']) {
                    $filters['categoryId'] = $config['cj_category_id'];
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
            } catch (\Throwable $e) {
                $this->warn("  Search failed for keyword '{$keyword}': {$e->getMessage()}");
                Log::warning('[CJ Trending] Search failed', [
                    'keyword' => $keyword,
                    'error' => $e->getMessage(),
                ]);
            }

            if (count($allPids) >= $config['target']) {
                $allPids = array_slice($allPids, 0, $config['target']);
                break;
            }
        }

        return $allPids;
    }
}
