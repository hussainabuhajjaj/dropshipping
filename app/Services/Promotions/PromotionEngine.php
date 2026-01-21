<?php

namespace App\Services\Promotions;

use App\Models\Customer;
use Illuminate\Support\Collection;
use App\Services\Promotions\Concerns\BuildsActivePromotionQuery;

class PromotionEngine
{
    use BuildsActivePromotionQuery;
    /**
     * Get all applicable promotions for a given cart context.
     * @param array $cart ['lines' => [...], 'subtotal' => float, 'user_id' => int|null]
     * @return Collection
     */
    public function getApplicablePromotions(array $cart): Collection
    {
        // NOTE: Base active/dated filters are centralized via BuildsActivePromotionQuery.
        $promotions = $this->activePromotionsQuery()
            ->orderByDesc('priority')
            ->with(['targets', 'conditions'])
            ->get();

        $lines = collect($cart['lines'] ?? []);
        $productIds = $lines->pluck('product_id')->filter()->unique();
        $categoryIds = $lines->pluck('category_id')->filter()->unique();
        $subtotal = (float) ($cart['subtotal'] ?? 0);
        $userId = $cart['user_id'] ?? null;

        return $promotions->filter(function ($promotion) use ($productIds, $categoryIds, $subtotal, $userId) {
            $targets = $promotion->targets ?? collect();
            if ($targets->count() > 0) {
                $matched = $targets->contains(function ($target) use ($productIds, $categoryIds) {
                    if ($target->target_type === 'category') {
                        return $categoryIds->contains($target->target_id);
                    }
                    if ($target->target_type === 'product') {
                        return $productIds->contains($target->target_id);
                    }
                    return false;
                });

                if (! $matched) {
                    return false;
                }
            }
            // Check conditions
            foreach ($promotion->conditions as $condition) {
                if ($condition->condition_type === 'min_cart_value') {
                    if ($subtotal < (float) $condition->condition_value) {
                        return false;
                    }
                }

                if ($condition->condition_type === 'first_order_only') {
                    if (! $this->isFirstOrderEligible($userId)) {
                        return false;
                    }
                }
            }
            return true;
        });
    }

    /**
     * Apply promotions to cart and return discount breakdown.
     * @param array $cart
     * @return array
     */
    public function applyPromotions(array $cart): array
    {
        $applicable = $this->getApplicablePromotions($cart);
        $subtotal = max(0, (float) ($cart['subtotal'] ?? 0));
        $discounts = [];
        $totalDiscount = 0;

        $discountForPromotion = function ($promotion) use ($subtotal): float {
            if ($promotion->value_type === 'percentage') {
                return $subtotal * ($promotion->value / 100);
            }
            if ($promotion->value_type === 'fixed') {
                return (float) $promotion->value;
            }
            return 0.0;
        };

        $urgencyExclusive = $applicable->filter(fn ($promo) => ($promo->promotion_intent ?? 'other') === 'urgency' && $promo->stacking_rule === 'exclusive');
        $exclusive = $applicable->where('stacking_rule', 'exclusive');

        if ($urgencyExclusive->isNotEmpty()) {
            $promotionsToApply = collect([$urgencyExclusive->sortByDesc(fn ($promo) => $discountForPromotion($promo))->first()]);
        } elseif ($exclusive->isNotEmpty()) {
            $promotionsToApply = collect([$exclusive->sortByDesc(fn ($promo) => $discountForPromotion($promo))->first()]);
        } else {
            $promotionsToApply = $applicable;
        }

        foreach ($promotionsToApply as $promotion) {
            $discount = min($subtotal, max(0.0, $discountForPromotion($promotion)));
            $discount = $this->applyMaxDiscount($promotion, $discount);
            $discounts[] = [
                'promotion_id' => $promotion->id,
                'label' => $promotion->name,
                'amount' => $discount,
                'value_type' => $promotion->value_type,
                'value' => $promotion->value,
                'intent' => $promotion->promotion_intent ?? 'other',
                'stacking_rule' => $promotion->stacking_rule,
            ];
            $totalDiscount += $discount;
        }

        $totalDiscount = min($subtotal, $totalDiscount);
        return [
            'discounts' => $discounts,
            'total_discount' => $totalDiscount,
        ];
    }

    private function applyMaxDiscount(Promotion $promotion, float $discount): float
    {
        $conditions = $promotion->conditions ?? collect();
        $caps = $conditions->where('condition_type', 'max_discount')->map(function ($condition) {
            return is_numeric($condition->condition_value) ? (float) $condition->condition_value : null;
        })->filter()->values();

        if ($caps->isEmpty()) {
            return $discount;
        }

        $cap = (float) $caps->min();
        return min($discount, $cap);
    }

    private function isFirstOrderEligible(?int $customerId): bool
    {
        if (! $customerId) {
            return true;
        }

        $customer = Customer::query()->find($customerId);
        if (! $customer) {
            return true;
        }

        return ! $customer->orders()->where('payment_status', 'paid')->exists();
    }
}
