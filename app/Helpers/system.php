<?php

use App\Http\Resources\User\CartResource;
use App\Models\Customer;
use App\Models\SiteSetting;
use App\Services\CampaignManager;
use App\Services\Coupons\CouponValidator;

function calculateTax(float $amount, $tax_rate = 0): float
{
    $rate = (float)$tax_rate;

    if ($rate <= 0) {
        return 0.0;
    }

    return round($amount * ($rate / 100), 2);
}


function calculateTaxFromSettings(float $taxableAmount, ?SiteSetting $settings): float
{
    if (! $settings || ! $settings->tax_rate) {
        return 0.0;
    }

    return round($taxableAmount * ((float) $settings->tax_rate / 100), 2);
}

function calculateDiscounts($cart, $cart_items, ?array $coupon, ?Customer $customer, float $subtotal): array
{
    $couponValidator = app(CouponValidator::class);
    $couponModel = $couponValidator->resolveFromSession($coupon);
    if ($couponModel) {
        $error = $couponValidator->validateForCart($couponModel, $cart_items, $subtotal, $customer);
        if ($error) {
            session()->forget('cart_coupon');
            $couponModel = null;
            $coupon = null;
        }
    }
    $couponDiscount = $couponModel ? $couponValidator->calculateDiscount($couponModel, $subtotal) : 0.0;
    $cart_items = (CartResource::collection($cart_items))->jsonSerialize();
    $campaign = app(CampaignManager::class)->bestForCart($cart_items, $subtotal, $customer);

    if ($couponDiscount >= ($campaign['amount'] ?? 0)) {
        return [
            'amount' => $couponDiscount,
            'label' => $couponModel ? __('Coupon: :code', ['code' => $couponModel->code]) : null,
            'source' => $couponModel ? 'coupon' : null,
            'coupon' => $couponModel ? $couponModel->serializeCoupon() : null,
            'coupon_model' => $couponModel,
            'promotion_discounts' => [],
        ];
    }

    return [
        'amount' => $campaign['amount'] ?? 0.0,
        'label' => $campaign['label'] ?? null,
        'source' => $campaign['source'] ?? null,
        'coupon' => null,
        'coupon_model' => null,
        'promotion_discounts' => $campaign['promotion_discounts'] ?? [],
    ];
}
