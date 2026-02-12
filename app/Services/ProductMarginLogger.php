<?php

namespace App\Services;

use App\Domain\Orders\Models\OrderItem;
use App\Domain\Products\Models\Product as DomainProduct;
use App\Domain\Products\Models\ProductVariant;
use App\Models\ProductMarginLog;
use Illuminate\Support\Carbon;

class ProductMarginLogger
{
    public function logProduct(DomainProduct $product, array $data): ProductMarginLog
    {
        return ProductMarginLog::query()->create($this->prepareProductRow($product, $data));
    }

    public function logVariant(ProductVariant $variant, array $data): ProductMarginLog
    {
        return ProductMarginLog::query()->create($this->prepareVariantRow($variant, $data));
    }

    /**
     * @return array<string, mixed>
     */
    public function prepareProductRow(DomainProduct $product, array $data): array
    {
        $actor = $this->resolveActor($data['actor_type'] ?? null, $data['actor_id'] ?? null);
        $oldSelling = $data['old_selling_price'] ?? $product->getOriginal('selling_price');
        $newSelling = $data['new_selling_price'] ?? $product->selling_price;

        return [
            'product_id' => $product->id,
            'variant_id' => null,
            'source' => $data['source'] ?? 'manual',
            'event' => $data['event'],
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'old_margin_percent' => $this->marginPercent($this->asFloatOrNull($oldSelling), $this->asFloatOrNull($product->cost_price)),
            'new_margin_percent' => $this->marginPercent($this->asFloatOrNull($newSelling), $this->asFloatOrNull($product->cost_price)),
            'old_selling_price' => $oldSelling,
            'new_selling_price' => $newSelling,
            'old_status' => $data['old_status'] ?? $product->getOriginal('status'),
            'new_status' => $data['new_status'] ?? $product->status,
            'sales_count' => $this->resolveSalesCount($data, fn (): int => $this->productSalesCount($product)),
            'notes' => $data['notes'] ?? null,
            'created_at' => $data['created_at'] ?? Carbon::now(),
            'updated_at' => $data['updated_at'] ?? Carbon::now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function prepareVariantRow(ProductVariant $variant, array $data): array
    {
        $actor = $this->resolveActor($data['actor_type'] ?? null, $data['actor_id'] ?? null);
        $product = $variant->product;
        $oldSelling = $data['old_selling_price'] ?? $variant->getOriginal('price');
        $newSelling = $data['new_selling_price'] ?? $variant->price;

        return [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'source' => $data['source'] ?? 'manual',
            'event' => $data['event'],
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'old_margin_percent' => $this->marginPercent($this->asFloatOrNull($oldSelling), $this->asFloatOrNull($variant->cost_price)),
            'new_margin_percent' => $this->marginPercent($this->asFloatOrNull($newSelling), $this->asFloatOrNull($variant->cost_price)),
            'old_selling_price' => $oldSelling,
            'new_selling_price' => $newSelling,
            'old_status' => $data['old_status'] ?? null,
            'new_status' => $data['new_status'] ?? null,
            'sales_count' => $this->resolveSalesCount($data, fn (): int => $this->variantSalesCount($variant)),
            'notes' => $data['notes'] ?? null,
            'created_at' => $data['created_at'] ?? Carbon::now(),
            'updated_at' => $data['updated_at'] ?? Carbon::now(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function insertMany(array $rows, int $chunkSize = 500): int
    {
        if ($rows === []) {
            return 0;
        }

        $inserted = 0;
        foreach (array_chunk($rows, max(1, $chunkSize)) as $chunk) {
            ProductMarginLog::query()->insert($chunk);
            $inserted += count($chunk);
        }

        return $inserted;
    }

    private function resolveActor(?string $actorType, ?int $actorId): array
    {
        if ($actorType && $actorId !== null) {
            return ['type' => $actorType, 'id' => $actorId];
        }

        if (! auth()->check()) {
            return ['type' => 'system', 'id' => null];
        }

        return ['type' => 'admin', 'id' => auth()->id()];
    }

    private function marginPercent(?float $selling, ?float $cost): ?float
    {
        if ($cost === null || $cost <= 0 || $selling === null) {
            return null;
        }

        return round((($selling - $cost) / $cost) * 100, 2);
    }

    private function productSalesCount(DomainProduct $product): int
    {
        return (int) OrderItem::query()
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->where('product_variants.product_id', $product->id)
            ->sum('order_items.quantity');
    }

    private function variantSalesCount(ProductVariant $variant): int
    {
        return (int) $variant->orderItems()->sum('quantity');
    }

    private function resolveSalesCount(array $data, callable $resolver): int
    {
        if (is_numeric($data['sales_count'] ?? null)) {
            return (int) $data['sales_count'];
        }

        if ((bool) ($data['skip_sales_count'] ?? false)) {
            return 0;
        }

        return (int) $resolver();
    }

    private function asFloatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
