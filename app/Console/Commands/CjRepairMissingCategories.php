<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Services\CjCategoryResolver;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use Illuminate\Console\Command;

class CjRepairMissingCategories extends Command
{
    protected $signature = 'cj:repair-missing-categories
        {--limit=500 : Maximum number of products to process}
        {--dry-run : Preview only, do not persist changes}
        {--sleep-ms=180 : Delay between CJ API calls in milliseconds}
        {--without-create : Do not create placeholder categories}';

    protected $description = 'Fetch CJ product payload by pid and backfill missing product.category_id.';

    public function handle(CJDropshippingClient $client, CjCategoryResolver $resolver): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $createMissing = ! (bool) $this->option('without-create');

        $products = Product::query()
            ->whereNotNull('cj_pid')
            ->whereNull('category_id')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($products->isEmpty()) {
            $this->info('No products with missing category_id found.');
            return self::SUCCESS;
        }

        $scanned = 0;
        $assigned = 0;
        $unresolved = 0;
        $errors = 0;

        foreach ($products as $product) {
            $scanned++;
            $pid = (string) $product->cj_pid;
            if ($pid === '') {
                $unresolved++;
                continue;
            }

            try {
                $response = $client->getProduct($pid);
            } catch (ApiException $e) {
                $errors++;
                $this->warn("API error for pid {$pid}: {$e->getMessage()}");
                $this->sleep($sleepMs);
                continue;
            } catch (\Throwable $e) {
                $errors++;
                $this->warn("Failed pid {$pid}: {$e->getMessage()}");
                $this->sleep($sleepMs);
                continue;
            }

            $payload = is_array($response->data ?? null) ? $response->data : null;
            if (! $payload) {
                $unresolved++;
                $this->sleep($sleepMs);
                continue;
            }

            $category = $resolver->resolveFromPayload($payload, $createMissing && ! $dryRun);
            if (! $category) {
                $unresolved++;
                $this->sleep($sleepMs);
                continue;
            }

            if (! $dryRun) {
                $attributes = is_array($product->attributes) ? $product->attributes : [];
                $attributes['cj_payload'] = $payload;
                $attributes['cj_category_id'] = $payload['categoryId'] ?? ($attributes['cj_category_id'] ?? null);

                $product->category_id = $category->id;
                $product->cj_last_payload = $payload;
                $product->attributes = $attributes;
                $product->save();
            }

            $assigned++;
            $this->line(sprintf(
                '[%s] #%d pid=%s -> category_id=%d (%s)',
                $dryRun ? 'DRY' : 'OK',
                (int) $product->id,
                $pid,
                (int) $category->id,
                (string) $category->name
            ));

            $this->sleep($sleepMs);
        }

        $this->table(['Metric', 'Count'], [
            ['Scanned', $scanned],
            ['Assigned category_id', $assigned],
            ['Unresolved', $unresolved],
            ['Errors', $errors],
        ]);

        if ($dryRun) {
            $this->info('Dry-run completed. No records were changed.');
        } else {
            $this->info('Repair completed.');
        }

        return self::SUCCESS;
    }

    private function sleep(int $sleepMs): void
    {
        if ($sleepMs > 0) {
            usleep($sleepMs * 1000);
        }
    }
}

