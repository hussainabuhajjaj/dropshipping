<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Services\CjCategoryResolver;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Models\CjProductSnapshot;
use App\Services\Api\ApiException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

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
        $snapshotsByPid = CjProductSnapshot::query()
            ->whereIn('pid', $products->pluck('cj_pid')->filter()->all())
            ->get()
            ->keyBy('pid');

        if ($products->isEmpty()) {
            $this->info('No products with missing category_id found.');
            return self::SUCCESS;
        }

        $scanned = 0;
        $assigned = 0;
        $unresolved = 0;
        $errors = 0;
        $removedFromShelves = 0;
        $markedRemoved = 0;
        $resolvedFromSnapshots = 0;

        foreach ($products as $product) {
            $scanned++;
            $pid = (string) $product->cj_pid;
            if ($pid === '') {
                $unresolved++;
                continue;
            }

            $payload = null;
            $wasRemovedFromShelves = false;

            try {
                $response = $client->getProduct($pid);
                $payload = is_array($response->data ?? null) ? $response->data : null;
            } catch (ApiException $e) {
                if ($this->isRemovedFromShelves($e)) {
                    $removedFromShelves++;
                    $wasRemovedFromShelves = true;
                    $this->warn("Removed from shelves for pid {$pid}: {$e->getMessage()}");
                    if (! $dryRun) {
                        $this->markProductRemoved($product, $e->getMessage());
                        $markedRemoved++;
                    }

                    $snapshotPayload = $this->extractSnapshotPayload(
                        $snapshotsByPid->get($pid)
                    );
                    if ($snapshotPayload !== null) {
                        $payload = $snapshotPayload;
                        $resolvedFromSnapshots++;
                    }
                } else {
                    $errors++;
                    $this->warn("API error for pid {$pid}: {$e->getMessage()}");
                    $this->sleep($sleepMs);
                    continue;
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->warn("Failed pid {$pid}: {$e->getMessage()}");
                $this->sleep($sleepMs);
                continue;
            }

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
                if (! $wasRemovedFromShelves) {
                    $product->cj_removed_from_shelves_at = null;
                    $product->cj_removed_reason = null;
                }
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
            ['Resolved from snapshots', $resolvedFromSnapshots],
            ['Removed from shelves', $removedFromShelves],
            ['Marked removed', $markedRemoved],
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

    private function isRemovedFromShelves(ApiException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'removed from shelves')
            || str_contains($message, 'off shelf')
            || str_contains($message, 'offline')
            || in_array($e->codeString, ['PRODUCT_OFF_SHELF', '404'], true);
    }

    private function markProductRemoved(Product $product, ?string $reason = null): void
    {
        $product->update([
            'status' => 'draft',
            'is_active' => false,
            'cj_sync_enabled' => false,
            'cj_synced_at' => now(),
            'cj_removed_from_shelves_at' => now(),
            'cj_removed_reason' => $reason ? Str::limit($reason, 500) : 'Removed from shelves',
        ]);
    }

    private function extractSnapshotPayload(?CjProductSnapshot $snapshot): ?array
    {
        if (! $snapshot) {
            return null;
        }

        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
        $snapshotCategoryId = is_scalar($snapshot->category_id) ? trim((string) $snapshot->category_id) : '';

        if ($snapshotCategoryId !== '') {
            $payload['categoryId'] = $payload['categoryId'] ?? $snapshotCategoryId;
            $payload['cj_category_id'] = $payload['cj_category_id'] ?? $snapshotCategoryId;
        }

        return $payload !== [] ? $payload : null;
    }
}
