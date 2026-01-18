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
        $hasShippingSupport = collect($promoDiscounts)->contains(fn ($d) => ($d['intent'] ?? null) === 'shipping_support');

        $candidates = array_filter([
            $promoTotal > 0 ? [
                'amount' => $promoTotal,
                'label' => $promoLabel,
                'source' => 'promotion',
                'promotion_discounts' => $promoDiscounts,
            ] : null,
            $hasShippingSupport ? null : $this->firstOrderDiscount($customer, $subtotal),
            $hasShippingSupport ? null : $this->highValueThreshold($subtotal),
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

        $cap = (float) (config('promotions.caps.first_order_max_discount') ?? 10.0);
        $amount = round($subtotal * 0.10, 2);
        $amount = min($amount, $cap);
        return $amount > 0 ? [
            'amount' => $amount,
            'label' => 'First order 10% off',
            'source' => 'first_order',
        ] : null;
    }

    private function highValueThreshold(float $subtotal): ?array
    {
        if ($subtotal < 50) {
            return null;
        }

        $cap = (float) (config('promotions.caps.high_value_max_discount') ?? 15.0);
        $amount = round($subtotal * 0.05, 2);
        $amount = min($amount, $cap);
        return [
            'amount' => $amount,
            'label' => '5% off orders over $50',
            'source' => 'high_value',
        ];
    }
}
