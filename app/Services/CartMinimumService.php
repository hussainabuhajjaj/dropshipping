<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Coupon;
use App\Models\PromotionCondition;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;

class CartMinimumService
{
    protected SiteSetting|null $settings = null;

    public function threshold(): float
    {
        return (float) ($this->settings()->min_cart_total ?? 25.0);
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->settings()->min_cart_total_enabled ?? true);
    }

    /**
     * Evaluate whether the cart meets the minimum requirement.
     *
     * @param  float  $subtotal
     * @param  float  $discount
     * @param  Collection|null  $promotions
     * @param  Coupon|null  $coupon
     * @return array{passes: bool, threshold: float, effective_total: float, message: string|null}
     */
    public function evaluate(float $subtotal, float $discount, ?Collection $promotions = null, ?Coupon $coupon = null): array
    {
        $threshold = $this->threshold();
        $effective = max(0.0, $subtotal - $discount);

        if (!$this->isEnabled() || $threshold <= 0.0 || $effective >= $threshold || $this->allowsOverride($promotions, $coupon)) {
            return [
                'passes' => true,
                'threshold' => $threshold,
                'effective_total' => $effective,
                'message' => null,
            ];
        }

        $formatted = number_format($threshold, 2);
        return [
            'passes' => false,
            'threshold' => $threshold,
            'effective_total' => $effective,
            'message' => "Add at least \${$formatted} worth of products (after discounts) before checking out.",
        ];
    }

    protected function settings(): SiteSetting
    {
        if (!$this->settings) {
            $this->settings = SiteSetting::query()->first() ?? new SiteSetting();
        }

        return $this->settings;
    }

    protected function allowsOverride(?Collection $promotions, ?Coupon $coupon): bool
    {
        if ($coupon && data_get($coupon->meta, 'skip_min_cart_total')) {
            return true;
        }

        if ($promotions === null) {
            return false;
        }

        return $promotions->contains(function ($promotion) {
            return $promotion->conditions->contains('condition_type', PromotionCondition::CONDITION_SKIP_MIN_CART_TOTAL);
        });
    }
}
