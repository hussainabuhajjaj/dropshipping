<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class NotifyLowStock extends Command
{
    protected $signature = 'stock:notify-low
        {--cooldown-hours=6 : Cooldown before notifying about the same item again}
        {--limit=100 : Max number of low-stock items per notification run}
        {--dry-run : Preview low-stock items without sending notifications}';

    protected $description = 'Notify admin users about low stock products and variants.';

    public function handle(): int
    {
        $cooldownHours = max(1, (int) $this->option('cooldown-hours'));
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $items = array_slice(array_merge(
            $this->collectLowStockVariantItems($cooldownHours),
            $this->collectLowStockProductItems($cooldownHours)
        ), 0, $limit);

        if ($items === []) {
            $this->components->info('No new low-stock items to notify.');

            return self::SUCCESS;
        }

        $this->table(
            ['Type', 'Product', 'Variant', 'SKU', 'Stock', 'Threshold'],
            array_map(fn (array $item): array => [
                $item['type'],
                $item['product_name'],
                $item['variant_title'] ?? '-',
                $item['sku'] ?? '-',
                (string) $item['stock'],
                (string) $item['threshold'],
            ], $items)
        );

        if ($dryRun) {
            $this->components->info('Dry run completed. No notifications were sent.');

            return self::SUCCESS;
        }

        $recipients = User::supportAgents()
            ->where('email', config('mail.from.address'))
            ->get();

        if ($recipients->isEmpty()) {
            $this->components->warn('No low-stock notification recipient found (expected: ' . config('mail.from.address') . ').');

            return self::SUCCESS;
        }

        Notification::send($recipients, new LowStockNotification($items));

        foreach ($items as $item) {
            Cache::put($this->cacheKey($item['type'], $item['id']), true, now()->addHours($cooldownHours));
        }

        $this->components->info('Low-stock notifications sent to: ' . $recipients->pluck('email')->filter()->implode(', '));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{
     *   type:string,
     *   id:int,
     *   product_id:int,
     *   product_name:string,
     *   variant_title:?string,
     *   sku:?string,
     *   stock:int,
     *   threshold:int,
     *   action_url:string
     * }>
     */
    private function collectLowStockVariantItems(int $cooldownHours): array
    {
        return ProductVariant::query()
            ->with('product')
            ->whereNotNull('stock_on_hand')
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('stock_on_hand', '<=', 'low_stock_threshold')
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->orderBy('stock_on_hand')
            ->get()
            ->filter(function (ProductVariant $variant) use ($cooldownHours): bool {
                if (! $variant->product) {
                    return false;
                }

                return ! Cache::has($this->cacheKey('variant', (int) $variant->id));
            })
            ->map(function (ProductVariant $variant): array {
                $product = $variant->product;

                return [
                    'type' => 'variant',
                    'id' => (int) $variant->id,
                    'product_id' => (int) $product->id,
                    'product_name' => (string) $product->name,
                    'variant_title' => $variant->title ? (string) $variant->title : null,
                    'sku' => $variant->sku ? (string) $variant->sku : null,
                    'stock' => (int) ($variant->stock_on_hand ?? 0),
                    'threshold' => (int) ($variant->low_stock_threshold ?? 0),
                    'action_url' => url("/admin/products/{$product->id}/edit"),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{
     *   type:string,
     *   id:int,
     *   product_id:int,
     *   product_name:string,
     *   variant_title:?string,
     *   sku:?string,
     *   stock:int,
     *   threshold:int,
     *   action_url:string
     * }>
     */
    private function collectLowStockProductItems(int $cooldownHours): array
    {
        return Product::query()
            ->doesntHave('variants')
            ->where('is_active', true)
            ->whereNotNull('stock_on_hand')
            ->where('stock_on_hand', '<=', 5)
            ->orderBy('stock_on_hand')
            ->get()
            ->filter(fn (Product $product): bool => ! Cache::has($this->cacheKey('product', (int) $product->id)))
            ->map(fn (Product $product): array => [
                'type' => 'product',
                'id' => (int) $product->id,
                'product_id' => (int) $product->id,
                'product_name' => (string) $product->name,
                'variant_title' => null,
                'sku' => null,
                'stock' => (int) ($product->stock_on_hand ?? 0),
                'threshold' => 5,
                'action_url' => url("/admin/products/{$product->id}/edit"),
            ])
            ->values()
            ->all();
    }

    private function cacheKey(string $type, int $id): string
    {
        return "low-stock-notified:{$type}:{$id}";
    }
}
