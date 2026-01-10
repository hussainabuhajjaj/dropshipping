<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;

class CampaignManager
{
    public function bestForCart(array $cart, float $subtotal, ?Customer $customer = null): array
    {
        $customer = $customer ?? Auth::guard('customer')->user();

        // Use new PromotionEngine for general-purpose promotions
        $promotionEngine = app(\App\Services\Promotions\PromotionEngine::class);
        $promoResult = $promotionEngine->applyPromotions([
            'lines' => $cart,
            'subtotal' => $subtotal,
            'user_id' => $customer?->id,
        ]);
        $promoDiscounts = $promoResult['discounts'] ?? [];
        $promoTotal = $promoResult['total_discount'] ?? 0.0;
        $promoLabel = collect($promoDiscounts)->pluck('label')->filter()->implode(' + ');

        $candidates = array_filter([
            $promoTotal > 0 ? ['amount' => $promoTotal, 'label' => $promoLabel] : null,
            $this->firstOrderDiscount($customer, $subtotal),
            $this->highValueThreshold($subtotal),
        ]);

        if (empty($candidates)) {
            return ['amount' => 0.0, 'label' => null];
        }

        usort($candidates, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return $candidates[0];
    }

    private function firstOrderDiscount(?Customer $customer, float $subtotal): ?array
    {
        if (! $customer) {
            return null;
        }

        $hasOrders = $customer->orders()->where('payment_status', 'paid')->exists();
        if ($hasOrders) {
            return null;
        }

        $amount = round($subtotal * 0.10, 2);
        return $amount > 0 ? ['amount' => $amount, 'label' => 'First order 10% off'] : null;
    }

    private function highValueThreshold(float $subtotal): ?array
    {
        if ($subtotal < 50) {
            return null;
        }

        $amount = round($subtotal * 0.05, 2);
        return ['amount' => $amount, 'label' => '5% off orders over $50'];
    }
}
