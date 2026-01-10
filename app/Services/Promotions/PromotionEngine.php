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
            ->get();

        return $promotions->filter(function ($promotion) use ($cart) {
            // Check targets
            foreach ($promotion->targets as $target) {
                if ($target->target_type === 'category') {
                    $categoryIds = collect($cart['lines'])->pluck('category_id')->unique();
                    if (!$categoryIds->contains($target->target_id)) {
                        return false;
                    }
                }
                if ($target->target_type === 'product') {
                    $productIds = collect($cart['lines'])->pluck('product_id')->unique();
                    if (!$productIds->contains($target->target_id)) {
                        return false;
                    }
                }
            }
            // Check conditions
            foreach ($promotion->conditions as $condition) {
                if ($condition->condition_type === 'min_cart_value') {
                    if ($cart['subtotal'] < (float)$condition->condition_value) {
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
        $discounts = [];
        $totalDiscount = 0;
        foreach ($applicable as $promotion) {
            if ($promotion->value_type === 'percentage') {
                $discount = $cart['subtotal'] * ($promotion->value / 100);
            } elseif ($promotion->value_type === 'fixed') {
                $discount = $promotion->value;
            } else {
                $discount = 0;
            }
            $discounts[] = [
                'promotion_id' => $promotion->id,
                'label' => $promotion->name,
                'amount' => $discount,
            ];
            $totalDiscount += $discount;
        }
        return [
            'discounts' => $discounts,
            'total_discount' => $totalDiscount,
        ];
    }
}
