<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;
use App\Models\Customer;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductResource extends \App\Http\Resources\Storefront\ProductResource
{
    use WithoutSuccessWrapper;

    /**
     * @var array<int, array<int>>
     */
    protected static array $wishlistProductIdsByCustomer = [];

    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $product = $this->resource;
        $locale = app()->getLocale();
        $marketing = is_array($product->marketing_metadata ?? null) ? $product->marketing_metadata : [];
        $localizedMarketing = is_array($marketing[$locale] ?? null) ? $marketing[$locale] : [];
        $stockOnHand = $this->resolveAvailableStock($data, $product);
        $discount = $this->resolveDiscountSummary($data);
        $leadTimeDays = is_numeric($product->shipping_estimate_days ?? null)
            ? (int) $product->shipping_estimate_days
            : null;
        $primaryImage = is_array($data['media'] ?? null) ? ($data['media'][0] ?? null) : null;
        $summarySource = $localizedMarketing['description'] ?? $data['description'] ?? null;
        $summary = trim(strip_tags((string) $summarySource));

        $data['primary_image'] = $primaryImage;
        $data['subtitle'] = $localizedMarketing['title'] ?? $data['category'] ?? null;
        $data['short_description'] = $summary !== '' ? Str::limit($summary, 140) : null;
        $data['stock_on_hand'] = $stockOnHand;
        $data['inventory_status'] = $this->inventoryStatus($stockOnHand);
        $data['inventory_label'] = $this->inventoryLabel($stockOnHand);
        $data['is_low_stock'] = $stockOnHand !== null && $stockOnHand > 0 && $stockOnHand <= 5;
        $data['has_discount'] = $discount['has_discount'];
        $data['discount_percent'] = $discount['discount_percent'];
        $data['savings_amount'] = $discount['savings_amount'];
        $data['delivery'] = [
            'lead_time_days' => $leadTimeDays,
            'label' => $leadTimeDays !== null ? "Ships in {$leadTimeDays} days" : null,
        ];
        $data['is_in_wishlist'] = $data['is_in_wishlist'] || $this->isInWishlist($request, (int) $product->id);

        return $data;
    }

    private function resolveAvailableStock(array $data, mixed $product): ?int
    {
        $variants = collect($data['variants'] ?? []);
        $defaultVariantId = $data['default_variant_id'] ?? null;

        $defaultVariantStock = $variants
            ->firstWhere('id', $defaultVariantId)['stock_on_hand'] ?? null;

        if (is_numeric($defaultVariantStock)) {
            return (int) $defaultVariantStock;
        }

        $highestVariantStock = $variants
            ->pluck('stock_on_hand')
            ->filter(fn ($stock) => is_numeric($stock))
            ->map(fn ($stock) => (int) $stock)
            ->max();

        if ($highestVariantStock !== null) {
            return $highestVariantStock;
        }

        if (is_numeric($product->stock_on_hand ?? null)) {
            return (int) $product->stock_on_hand;
        }

        return null;
    }

    /**
     * @return array{has_discount: bool, discount_percent: ?int, savings_amount: ?float}
     */
    private function resolveDiscountSummary(array $data): array
    {
        $price = is_numeric($data['price'] ?? null) ? (float) $data['price'] : null;
        $compareAt = is_numeric($data['compare_at_price'] ?? null) ? (float) $data['compare_at_price'] : null;

        if ($price === null || $compareAt === null || $compareAt <= $price || $compareAt <= 0) {
            return [
                'has_discount' => false,
                'discount_percent' => null,
                'savings_amount' => null,
            ];
        }

        return [
            'has_discount' => true,
            'discount_percent' => (int) round((($compareAt - $price) / $compareAt) * 100),
            'savings_amount' => round($compareAt - $price, 2),
        ];
    }

    private function inventoryStatus(?int $stockOnHand): string
    {
        if ($stockOnHand === null) {
            return 'unknown';
        }

        if ($stockOnHand <= 0) {
            return 'out_of_stock';
        }

        if ($stockOnHand <= 5) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    private function inventoryLabel(?int $stockOnHand): string
    {
        return match ($this->inventoryStatus($stockOnHand)) {
            'out_of_stock' => 'Out of stock',
            'low_stock' => 'Low stock',
            'in_stock' => 'In stock',
            default => 'Stock updates soon',
        };
    }

    private function isInWishlist(Request $request, int $productId): bool
    {
        $customer = $request->user() ?? $request->user('sanctum');

        if (! $customer instanceof Customer) {
            return false;
        }

        if (! array_key_exists($customer->id, self::$wishlistProductIdsByCustomer)) {
            self::$wishlistProductIdsByCustomer[$customer->id] = WishlistItem::query()
                ->where('customer_id', $customer->id)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return in_array($productId, self::$wishlistProductIdsByCustomer[$customer->id], true);
    }
}
