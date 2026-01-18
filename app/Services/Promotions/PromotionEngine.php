<?php

namespace App\Services\Promotions;

use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\PromotionCondition;
use Illuminate\Support\Collection;

class PromotionEngine
{
    /**
     * Get all applicable promotions for a given cart context.
     * @param array $cart ['lines' => [...], 'subtotal' => float, 'user_id' => int|null]
     * @return Collection
     */
    public function getApplicablePromotions(array $cart): Collection
    {
        $now = now();
        $promotions = Promotion::where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->orderByDesc('priority')
            ->with(['targets', 'conditions'])
            ->get();

        $lines = collect($cart['lines'] ?? []);
        $productIds = $lines->pluck('product_id')->filter()->unique();
        $categoryIds = $lines->pluck('category_id')->filter()->unique();
        $subtotal = (float) ($cart['subtotal'] ?? 0);

        return $promotions->filter(function ($promotion) use ($productIds, $categoryIds, $subtotal) {
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
                    if ($subtotal < (float)$condition->condition_value) {
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

        $exclusive = $applicable->where('stacking_rule', 'exclusive');
        $promotionsToApply = $exclusive->isNotEmpty()
            ? collect([$exclusive->sortByDesc(fn ($promo) => $discountForPromotion($promo))->first()])
            : $applicable;

        foreach ($promotionsToApply as $promotion) {
            $discount = min($subtotal, max(0.0, $discountForPromotion($promotion)));
            $discounts[] = [
                'promotion_id' => $promotion->id,
                'label' => $promotion->name,
                'amount' => $discount,
                'value_type' => $promotion->value_type,
                'value' => $promotion->value,
            ];
            $totalDiscount += $discount;
        }

        $totalDiscount = min($subtotal, $totalDiscount);
        return [
            'discounts' => $discounts,
            'total_discount' => $totalDiscount,
        ];
    }
}
