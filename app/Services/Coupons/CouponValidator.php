<?php

declare(strict_types=1);

namespace App\Services\Coupons;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use Illuminate\Support\Collection;

class CouponValidator
{
    public function resolveFromSession(?array $sessionCoupon): ?Coupon
    {
        if (! $sessionCoupon) {
            return null;
        }

        $query = Coupon::query();
        if (isset($sessionCoupon['id'])) {
            $query->where('id', $sessionCoupon['id']);
        }
        if (isset($sessionCoupon['code'])) {
            $query->where('code', $sessionCoupon['code']);
        }

        return $query->first();
    }

    public function validateForCart(Coupon $coupon, iterable $cartLines, float $subtotal, ?Customer $customer): ?string
    {
        if (! $coupon->isCurrentlyValid()) {
            return 'Coupon not found or inactive.';
        }

        if ($coupon->min_order_total && $subtotal < (float) $coupon->min_order_total) {
            return 'Coupon requires a higher order total.';
        }

        if ($coupon->is_one_time_per_customer && $customer) {
            $alreadyRedeemed = CouponRedemption::query()
                ->where('coupon_id', $coupon->id)
                ->where('customer_id', $customer->id)
                ->where('status', 'redeemed')
                ->exists();

            if ($alreadyRedeemed) {
                return 'Coupon already redeemed.';
            }
        }

        $lines = $this->normalizeLines($cartLines);
        if ($coupon->applicable_to === 'products') {
            $eligibleProductIds = $coupon->products()->pluck('products.id')->all();
            $hasMatch = collect($lines)->contains(fn ($line) => in_array($line['product_id'], $eligibleProductIds, true));
            if (! $hasMatch) {
                return 'Coupon does not apply to items in your cart.';
            }
        }

        if ($coupon->applicable_to === 'categories') {
            $eligibleCategoryIds = $coupon->categories()->pluck('categories.id')->all();
            $hasMatch = collect($lines)->contains(fn ($line) => in_array($line['category_id'], $eligibleCategoryIds, true));
            if (! $hasMatch) {
                return 'Coupon does not apply to items in your cart.';
            }
        }

        if ($coupon->exclude_on_sale && $this->hasSaleItems($lines)) {
            return 'Coupon cannot be used on sale items.';
        }

        return null;
    }

    public function calculateDiscount(Coupon $coupon, float $subtotal): float
    {
        if ($coupon->min_order_total && $subtotal < (float) $coupon->min_order_total) {
            return 0.0;
        }

        if ($coupon->type === 'fixed') {
            return min((float) $coupon->amount, $subtotal);
        }

        if ($coupon->type === 'percentage' || $coupon->type === 'percent') {
            $percent = (float) $coupon->amount;
            return round($subtotal * ($percent / 100), 2);
        }

        return 0.0;
    }

    /**
     * @param iterable<int, mixed> $cartLines
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLines(iterable $cartLines): array
    {
        $lines = [];

        foreach ($cartLines as $line) {
            if (is_array($line)) {
                $productId = $line['product_id'] ?? null;
                $categoryId = $line['category_id'] ?? null;
                $price = $line['price'] ?? null;
                $compareAt = $line['compare_at_price'] ?? null;
                $isOnSale = $line['is_on_sale'] ?? (
                    $compareAt !== null && $price !== null && (float) $compareAt > (float) $price
                );
            } else {
                $productId = $line->product_id ?? null;
                $categoryId = $line->product?->category_id ?? null;
                $price = method_exists($line, 'getSinglePrice') ? $line->getSinglePrice() : ($line->price ?? null);
                $compareAt = $line->variant?->compare_at_price ?? null;
                $isOnSale = $compareAt !== null && $price !== null && (float) $compareAt > (float) $price;
            }

            $lines[] = [
                'product_id' => $productId ? (int) $productId : null,
                'category_id' => $categoryId ? (int) $categoryId : null,
                'price' => $price !== null ? (float) $price : null,
                'compare_at_price' => $compareAt !== null ? (float) $compareAt : null,
                'is_on_sale' => (bool) $isOnSale,
            ];
        }

        return $lines;
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    private function hasSaleItems(array $lines): bool
    {
        return collect($lines)->contains(fn ($line) => ! empty($line['is_on_sale']));
    }
}
