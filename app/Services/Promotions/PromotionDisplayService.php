<?php

declare(strict_types=1);

namespace App\Services\Promotions;

use App\Models\Promotion;
use Illuminate\Support\Collection;

class PromotionDisplayService
{
    /**
     * Get promotions for a specific placement with optional product/category context.
     *
     * @param  string  $placement  home|category|product|cart|checkout
     * @param  array<int>  $productIds
     * @param  array<int>  $categoryIds
     */
    public function getForPlacement(string $placement, array $productIds = [], array $categoryIds = [], int $limit = 5): array
    {
        // NOTE: Active/dated promotion query is duplicated in PromotionEngine and
        // PromotionController::activePromotions(). Keep filters aligned when changing.
        $now = now();
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        $displayEnabled = (bool) (config('promotions.display.enabled') ?? true);

        $promotions = Promotion::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->when(! $displayEnabled && $placement === 'home', fn ($q) => $q->whereIn('type', ['flash_sale', 'auto_discount']))
            ->with(['targets', 'conditions'])
            ->orderByDesc('priority')
            ->get();

        $filtered = $promotions->filter(function (Promotion $promotion) use ($placement, $productIds, $categoryIds) {
            $displayEnabled = (bool) (config('promotions.display.enabled') ?? true);
            if ($displayEnabled && ! $this->placementAllows($promotion, $placement)) {
                return false;
            }

            $targets = $promotion->targets ?? collect();
            if ($targets->isEmpty()) {
                if (! $displayEnabled && $placement !== 'home') {
                    return false;
                }
                return true;
            }

            if ($productIds === [] && $categoryIds === [] && $placement === 'home') {
                return true;
            }

            return $targets->contains(function ($target) use ($productIds, $categoryIds) {
                if ($target->target_type === 'product') {
                    return in_array((int) $target->target_id, $productIds, true);
                }
                if ($target->target_type === 'category') {
                    return in_array((int) $target->target_id, $categoryIds, true);
                }
                return false;
            });
        });

        $sorted = $filtered->sort(function (Promotion $a, Promotion $b) {
            $priority = $b->priority <=> $a->priority;
            if ($priority !== 0) {
                return $priority;
            }

            $discountDiff = $this->potentialDiscount($b) <=> $this->potentialDiscount($a);
            if ($discountDiff !== 0) {
                return $discountDiff;
            }

            $aEnd = $a->end_at?->getTimestamp() ?? PHP_INT_MAX;
            $bEnd = $b->end_at?->getTimestamp() ?? PHP_INT_MAX;

            return $aEnd <=> $bEnd;
        });

        if ($limit > 0) {
            $sorted = $sorted->take($limit);
        }

        return $sorted->map(fn (Promotion $promo) => $this->serializePromotion($promo))->values()->all();
    }

    public function serializePromotion(Promotion $promo): array
    {
        $targets = $promo->targets ?? collect();
        $conditions = $promo->conditions ?? collect();
        $hasConditions = $conditions->isNotEmpty();
        $isSitewide = $targets->isEmpty();

        $intent = $promo->promotion_intent ?? 'other';
        $badge = $this->badgeText($intent, $promo);
        $applyHint = $this->applyHint($intent, $hasConditions, $isSitewide);

        return [
            'id' => $promo->id,
            'name' => $promo->name,
            'description' => $promo->description,
            'type' => $promo->type,
            'value_type' => $promo->value_type,
            'value' => $promo->value,
            'start_at' => $promo->start_at,
            'end_at' => $promo->end_at,
            'priority' => $promo->priority,
            'stacking_rule' => $promo->stacking_rule,
            'intent' => $intent,
            'has_conditions' => $hasConditions,
            'is_sitewide' => $isSitewide,
            'badge_text' => $badge,
            'apply_hint' => $applyHint,
            'targets' => $targets->map(fn ($t) => [
                'target_type' => $t->target_type,
                'target_id' => $t->target_id,
            ])->values()->all(),
            // Note: targets_summary is currently not consumed by the frontend.
            // Keep for debugging/analytics payloads if needed later.
            'targets_summary' => $targets->map(fn ($t) => [
                'type' => $t->target_type,
                'id' => $t->target_id,
            ])->values()->all(),
        ];
    }

    private function placementAllows(Promotion $promotion, string $placement): bool
    {
        $placements = $promotion->display_placements;
        if (! is_array($placements) || $placements === []) {
            return true;
        }

        return in_array($placement, $placements, true);
    }

    private function potentialDiscount(Promotion $promotion): float
    {
        if ($promotion->value_type === 'percentage') {
            return (float) $promotion->value;
        }
        if ($promotion->value_type === 'fixed') {
            return (float) $promotion->value;
        }
        return 0.0;
    }

    private function badgeText(string $intent, Promotion $promotion): string
    {
        return match ($intent) {
            'shipping_support' => 'Logistics Support',
            'cart_growth' => 'Cart Booster',
            'urgency' => $promotion->type === 'flash_sale' ? 'Flash Deal' : 'Limited Drop',
            'acquisition' => 'Special Offer',
            default => 'Promotion',
        };
    }

    private function applyHint(string $intent, bool $hasConditions, bool $isSitewide): string
    {
        if ($hasConditions || $isSitewide) {
            return 'Applied at checkout';
        }

        return match ($intent) {
            'shipping_support' => 'Applied at checkout',
            'cart_growth' => 'Unlock in cart',
            'urgency' => 'Limited time',
            default => 'Applied automatically',
        };
    }
}
