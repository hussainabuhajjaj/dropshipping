<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CjProductSnapshot;
use App\Models\Category;
use App\Models\Product;
use App\Domain\Products\Services\CjProductMediaService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CjImportSnapshots extends Command
{
    protected $signature = 'cj:import-snapshots {--limit=200}';

    protected $description = 'Import CJ product snapshots into catalog (categories + products)';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $snapshots = CjProductSnapshot::query()
            ->orderByDesc('synced_at')
            ->limit($limit)
            ->get();

        $mediaService = app(CjProductMediaService::class);

        foreach ($snapshots as $snap) {
            $payload = $snap->payload ?? [];
            $categoryId = $snap->category_id ?? ($payload['categoryId'] ?? null);
            $name = $payload['nameEn'] ?? $snap->name ?? 'CJ Product';
            $slug = Str::slug($name . '-' . $snap->pid);
            $price = $payload['sellPrice'] ?? null;
            $sku = $payload['sku'] ?? null;
            $description = $mediaService->cleanDescription(
                $payload['descriptionEn'] ?? $payload['description'] ?? null
            );

            $category = null;
            if ($categoryId) {
                $category = Category::firstOrCreate(
                    ['cj_id' => $categoryId],
                    [
                        'name' => $payload['categoryName'] ?? ('CJ Category ' . Str::limit($categoryId, 6, '')),
                        'slug' => Str::slug($payload['categoryName'] ?? ('cj-' . $categoryId)),
                    ]
                );
            }

            $product = Product::updateOrCreate(
                ['cj_pid' => $snap->pid],
                [
                    'name' => $name,
                    'slug' => $slug,
                    'category_id' => $category?->id,
                    'description' => $description,
                    'selling_price' => is_numeric($price) ? (float) $price : 0,
                    'cost_price' => is_numeric($price) ? (float) $price : 0,
                    'currency' => $payload['currency'] ?? 'USD',
                    'status' => 'draft',
                    'is_active' => false,
                    'is_featured' => false,
                    'attributes' => array_merge($payload['inventoryInfo'] ?? [], [
                        'cj_pid' => $snap->pid,
                        'cj_category_id' => $categoryId,
                        'cj_payload_synced_at' => optional($snap->synced_at)->toDateTimeString(),
                    ]),
                    'source_url' => $payload['sourceUrl'] ?? null,
                ]
            );

            // We defer variant creation to a more detailed sync; ensure SKU is captured.
            if ($sku && ! $product->variants()->where('sku', $sku)->exists()) {
                $product->variants()->create([
                    'sku' => $sku,
                    'title' => $payload['nameEn'] ?? 'Default',
                    'price' => is_numeric($price) ? (float) $price : 0,
                    'cost_price' => is_numeric($price) ? (float) $price : 0,
                    'currency' => $payload['currency'] ?? 'USD',
                    'supplier_currency' => $payload['currency'] ?? 'USD',
                    'metadata' => [
                        'cj_pid' => $snap->pid,
                    ],
                ]);
            }
        }

        $this->info("Imported {$snapshots->count()} CJ snapshots into catalog.");

        return self::SUCCESS;
    }
}
