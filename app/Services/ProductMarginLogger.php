<?php

namespace App\Services;

use App\Domain\Orders\Models\OrderItem;
use App\Domain\Products\Models\Product as DomainProduct;
use App\Domain\Products\Models\ProductVariant;
use App\Models\ProductMarginLog;
use Illuminate\Support\Facades\Auth;

class ProductMarginLogger
{
    public function logProduct(DomainProduct $product, array $data): ProductMarginLog
    {
        $actor = $this->resolveActor($data['actor_type'] ?? null, $data['actor_id'] ?? null);
        return ProductMarginLog::create([
            'product_id' => $product->id,
            'variant_id' => null,
            'source' => $data['source'] ?? 'manual',
            'event' => $data['event'],
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'old_margin_percent' => $this->marginPercent($data['old_selling_price'] ?? $product->getOriginal('selling_price'), $product->cost_price),
            'new_margin_percent' => $this->marginPercent($data['new_selling_price'] ?? $product->selling_price, $product->cost_price),
            'old_selling_price' => $data['old_selling_price'] ?? $product->getOriginal('selling_price'),
            'new_selling_price' => $data['new_selling_price'] ?? $product->selling_price,
            'old_status' => $data['old_status'] ?? $product->getOriginal('status'),
            'new_status' => $data['new_status'] ?? $product->status,
            'sales_count' => $this->productSalesCount($product),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function logVariant(ProductVariant $variant, array $data): ProductMarginLog
    {
        $actor = $this->resolveActor($data['actor_type'] ?? null, $data['actor_id'] ?? null);
        $product = $variant->product;
        return ProductMarginLog::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'source' => $data['source'] ?? 'manual',
            'event' => $data['event'],
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'old_margin_percent' => $this->marginPercent($data['old_selling_price'] ?? $variant->getOriginal('price'), $variant->cost_price),
            'new_margin_percent' => $this->marginPercent($data['new_selling_price'] ?? $variant->price, $variant->cost_price),
            'old_selling_price' => $data['old_selling_price'] ?? $variant->getOriginal('price'),
            'new_selling_price' => $data['new_selling_price'] ?? $variant->price,
            'old_status' => $data['old_status'] ?? null,
            'new_status' => $data['new_status'] ?? null,
            'sales_count' => $this->variantSalesCount($variant),
            'notes' => $data['notes'] ?? null,
        ]);
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
}
