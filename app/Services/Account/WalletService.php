<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\GiftCard;

class WalletService
{
    public function getWallet(Customer $customer): array
    {
        $giftCards = GiftCard::query()
            ->where('customer_id', $customer->id)
            ->latest()
            ->get()
            ->map(fn (GiftCard $card) => [
                'id' => $card->id,
                'code' => $card->code,
                'balance' => $card->balance,
                'currency' => $card->currency,
                'status' => $card->status,
                'expires_at' => $card->expires_at,
            ])
            ->values()
            ->all();

        $savedCouponIds = CouponRedemption::query()
            ->where('customer_id', $customer->id)
            ->pluck('coupon_id')
            ->all();

        $availableCoupons = Coupon::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $now = now();
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) {
                $now = now();
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->when($savedCouponIds, fn ($query) => $query->whereNotIn('id', $savedCouponIds))
            ->orderBy('code')
            ->get()
            ->map(fn (Coupon $coupon) => $this->transformCoupon($coupon))
            ->values()
            ->all();

        $savedCoupons = CouponRedemption::query()
            ->with('coupon')
            ->where('customer_id', $customer->id)
            ->latest()
            ->get()
            ->map(function (CouponRedemption $redemption) {
                return [
                    'id' => $redemption->id,
                    'status' => $redemption->status,
                    'redeemed_at' => $redemption->redeemed_at,
                    'coupon' => $redemption->coupon ? $this->transformCoupon($redemption->coupon) : null,
                ];
            })
            ->values()
            ->all();

        return [
            'gift_cards' => $giftCards,
            'available_coupons' => $availableCoupons,
            'saved_coupons' => $savedCoupons,
        ];
    }

    public function getVouchers(Customer $customer): array
    {
        $wallet = $this->getWallet($customer);
        $vouchers = [];

        foreach ($wallet['available_coupons'] as $coupon) {
            $vouchers[] = [
                'id' => 'coupon-' . ($coupon['id'] ?? uniqid('', true)),
                'code' => $coupon['code'] ?? null,
                'title' => $coupon['description'] ?: ($coupon['code'] ?? 'Voucher'),
                'description' => $coupon['description'] ?? null,
                'value' => $this->formatCouponValue($coupon),
                'type' => $coupon['type'] ?? null,
                'amount' => $coupon['amount'] ?? null,
                'min_order_total' => $coupon['min_order_total'] ?? null,
                'starts_at' => $coupon['starts_at'] ?? null,
                'ends_at' => $coupon['ends_at'] ?? null,
                'currency' => 'USD',
                'status' => 'available',
            ];
        }

        foreach ($wallet['saved_coupons'] as $saved) {
            $coupon = is_array($saved['coupon'] ?? null) ? $saved['coupon'] : [];
            $vouchers[] = [
                'id' => 'saved-' . ($saved['id'] ?? uniqid('', true)),
                'code' => $coupon['code'] ?? null,
                'title' => $coupon['description'] ?: ($coupon['code'] ?? 'Voucher'),
                'description' => $coupon['description'] ?? null,
                'value' => $this->formatCouponValue($coupon),
                'type' => $coupon['type'] ?? null,
                'amount' => $coupon['amount'] ?? null,
                'min_order_total' => $coupon['min_order_total'] ?? null,
                'starts_at' => $coupon['starts_at'] ?? null,
                'ends_at' => $coupon['ends_at'] ?? null,
                'currency' => 'USD',
                'status' => $saved['status'] ?? 'saved',
                'redeemed_at' => $saved['redeemed_at'] ?? null,
            ];
        }

        return $vouchers;
    }

    public function transformCoupon(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'description' => $coupon->description,
            'type' => $coupon->type,
            'amount' => $coupon->amount,
            'min_order_total' => $coupon->min_order_total,
            'starts_at' => $coupon->starts_at,
            'ends_at' => $coupon->ends_at,
        ];
    }

    private function formatCouponValue(array $coupon): string
    {
        $type = $coupon['type'] ?? null;
        $amount = isset($coupon['amount']) ? (float) $coupon['amount'] : 0.0;

        if (in_array($type, ['percent', 'percentage'], true)) {
            $value = rtrim(rtrim(number_format($amount, 0), '0'), '.');
            return "{$value}% OFF";
        }

        return '$' . number_format($amount, 2) . ' OFF';
    }
}
