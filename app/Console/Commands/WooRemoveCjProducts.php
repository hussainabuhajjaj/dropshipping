<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Domain\WooCommerce\Contracts\WooCommerceClientContract;
use App\Domain\WooCommerce\Models\WooCommerceProductMap;
use App\Domain\WooCommerce\Services\WooCommerceProductSyncService;
use App\Infrastructure\WooCommerce\WooCommerceApiException;
use Illuminate\Console\Command;

class WooRemoveCjProducts extends Command
{
    protected $signature = 'woo:remove-cj-products {--execute : Actually delete from WooCommerce. Without this flag, runs a dry-run.}';
    protected $description = 'Remove CJ-sourced products from the WooCommerce store and clear their sync maps. Dry-run by default.';

    public function handle(WooCommerceProductSyncService $syncService, WooCommerceClientContract $client): int
    {
        $execute = (bool) $this->option('execute');

        $mapIds = WooCommerceProductMap::query()->pluck('id');
        $total = $mapIds->count();

        $this->line("Found {$total} WooCommerce product map(s).");

        if ($total === 0) {
            return 0;
        }

        $stats = ['deleted' => 0, 'already_gone' => 0, 'skipped_non_cj' => 0, 'failed' => 0, 'orphans' => 0];
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($mapIds->chunk(100) as $chunk) {
            $maps = WooCommerceProductMap::query()->whereIn('id', $chunk)->get();

            foreach ($maps as $map) {
                $product = Product::withTrashed()->find($map->product_id);

                if ($product && $product->cj_pid === null) {
                    // Woo-origin product, keep it.
                    $stats['skipped_non_cj']++;
                    $bar->advance();
                    continue;
                }

                if (! $execute) {
                    if (! $product) {
                        $stats['orphans']++;
                    }
                    $stats['deleted']++;
                    $bar->advance();
                    continue;
                }

                try {
                    if ($product) {
                        $result = $syncService->deleteProduct($product);
                    } else {
                        $stats['orphans']++;
                        try {
                            $client->deleteProduct($map->woocommerce_product_id);
                        } catch (WooCommerceApiException $e) {
                            if (! $e->isNotFound()) {
                                throw $e;
                            }
                            $stats['already_gone']++;
                        }
                        $map->delete();
                        $result = null;
                    }

                    if ($result === null || $result->success) {
                        $stats['deleted']++;
                    } else {
                        $stats['failed']++;
                        $this->newLine();
                        $this->error("Failed Woo ID {$map->woocommerce_product_id}: {$result->error}");
                    }
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $this->newLine();
                    $this->error("Error Woo ID {$map->woocommerce_product_id}: {$e->getMessage()}");
                }

                usleep(200000);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->line('Deleted/queued for deletion: ' . $stats['deleted']);
        $this->line('Already gone in Woo: ' . $stats['already_gone']);
        $this->line('Skipped (non-CJ origin): ' . $stats['skipped_non_cj']);
        $this->line('Orphan maps (no local product): ' . $stats['orphans']);
        $this->line('Failed: ' . $stats['failed']);

        if (! $execute) {
            $this->comment('Dry run complete. Re-run with --execute to perform deletions.');
        } else {
            $this->info('Cleanup complete. CJ products should no longer exist in WooCommerce.');
        }

        return $stats['failed'] > 0 ? 1 : 0;
    }
}
