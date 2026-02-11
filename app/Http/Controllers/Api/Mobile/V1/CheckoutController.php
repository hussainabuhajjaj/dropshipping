<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Domain\Common\Models\Address;
use App\Domain\Orders\Models\OrderAuditLog;
use App\Events\Orders\OrderPlaced;
use App\Http\Requests\Api\Mobile\V1\Checkout\ConfirmRequest;
use App\Http\Requests\Api\Mobile\V1\Checkout\PreviewRequest;
use App\Http\Resources\Mobile\V1\CheckoutConfirmResource;
use App\Http\Resources\Mobile\V1\CheckoutPreviewResource;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderShipping;
use App\Models\Payment;
use App\Models\PromotionUsage;
use App\Models\SiteSetting;
use App\Services\CampaignManager;
use App\Services\CartMinimumService;
use App\Services\Coupons\CouponValidator;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends ApiController
{
    public function preview(PreviewRequest $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $payload = $this->buildPricingPayload($cart, $request->user());

        return $this->success(new CheckoutPreviewResource($payload));
    }

    public function confirm(ConfirmRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $cart = $this->resolveCart($request);
        $cartItems = $cart->items;

        if ($cartItems->isEmpty()) {
            return $this->error('Cart is empty', 422);
        }

        $customer = $request->user();
        $locale = app()->getLocale();

        $pricing = $this->buildPricingPayload($cart, $customer);
        $subtotal = (float) $pricing['subtotal'];
        $shipping = (float) $pricing['shipping'];
        $discount = (float) $pricing['discount'];
        $taxTotal = (float) $pricing['tax'];
        $total = (float) $pricing['total'];
        $coupon = $pricing['coupon'] ?? null;
        $couponModel = $pricing['coupon_model'] ?? null;
        $promotionDiscounts = $pricing['promotion_discounts'] ?? [];
        $discountSource = $pricing['discount_source'] ?? null;

        $settings = SiteSetting::query()->first();
        $taxIncluded = (bool) ($settings?->tax_included ?? false);

        $minimumRequirement = $pricing['minimum_cart_requirement'] ?? null;
        if ($minimumRequirement && ! ($minimumRequirement['passes'] ?? true)) {
            return $this->error((string) ($minimumRequirement['message'] ?? 'Minimum cart requirement not met'), 422);
        }

        $discountSnapshot = $this->buildDiscountSnapshot(
            $discount,
            $pricing['label'] ?? null,
            $discountSource,
            $coupon ? $this->serializeCoupon($couponModel) : null,
            $promotionDiscounts,
            $cartItems->first()?->variant?->currency
                ?? $cartItems->first()?->product?->currency
                ?? 'USD'
        );

        [$order, $payment] = DB::transaction(function () use (
            $validated,
            $cart,
            $cartItems,
            $discount,
            $coupon,
            $couponModel,
            $promotionDiscounts,
            $discountSource,
            $discountSnapshot,
            $subtotal,
            $shipping,
            $taxTotal,
            $total,
            $locale,
            $customer,
            $taxIncluded
        ) {
            if ($customer && $customer->locale !== $locale) {
                $customer->update(['locale' => $locale]);
            }

            $shippingAddress = Address::create([
                'user_id' => null,
                'customer_id' => $customer?->id,
                'name' => trim($validated['first_name'] . ' ' . ($validated['last_name'] ?? '')),
                'phone' => $validated['phone'],
                'line1' => $validated['line1'],
                'line2' => $validated['line2'] ?? null,
                'city' => $validated['city'],
                'state' => $validated['state'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'country' => strtoupper($validated['country']),
                'type' => 'shipping',
            ]);

            $order = Order::query()->create([
                'number' => Order::generateOrderNumber(),
                'user_id' => null,
                'customer_id' => $customer?->id,
                'guest_name' => $customer ? null : trim($validated['first_name'] . ' ' . ($validated['last_name'] ?? '')),
                'guest_phone' => $customer ? null : $validated['phone'],
                'is_guest' => ! $customer,
                'email' => $validated['email'],
                'locale' => $locale,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'currency' => $cartItems->first()?->variant?->currency
                    ?? $cartItems->first()?->product?->currency
                    ?? 'USD',
                'subtotal' => $subtotal,
                'shipping_total' => $shipping,
                'shipping_total_estimated' => $shipping,
                'tax_total' => $taxTotal,
                'discount_total' => $discount,
                'grand_total' => $total,
                'discount_snapshot' => $discountSnapshot,
                'discount_source' => $discountSource,
                'shipping_address_id' => $shippingAddress->id,
                'billing_address_id' => $shippingAddress->id,
                'shipping_method' => 'standard',
                'delivery_notes' => $validated['delivery_notes'] ?? null,
                'coupon_code' => $coupon['code'] ?? null,
                'placed_at' => now(),
            ]);

            $fallbackProvider = SiteSetting::query()->value('default_fulfillment_provider_id');
            foreach ($cartItems as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $line['variant_id'],
                    'fulfillment_provider_id' => $line['fulfillment_provider_id'] ?? $fallbackProvider,
                    'supplier_product_id' => null,
                    'fulfillment_status' => 'pending',
                    'quantity' => $line['quantity'],
                    'unit_price' => $line->getSinglePrice(),
                    'total' => $line->getSinglePrice() * $line['quantity'],
                    'source_sku' => null,
                    'snapshot' => [
                        'name' => $line?->product['name'],
                        'variant' => $line?->variant['title'],
                    ],
                    'meta' => [
                        'media' => $line['media'] ?? null,
                        'coupon_code' => $coupon['code'] ?? null,
                    ],
                ]);
            }

            foreach ($cart->shippings as $shippingEntry) {
                $shippingArray = $shippingEntry->toArray();
                $shippingArray['order_id'] = $order->id;
                $shippingArray['name'] = $shippingArray['logistic_name'] ?? 'Shipping';
                $shippingArray['price'] = $shippingArray['logistic_price'] ?? $shippingArray['price'] ?? 0;
                OrderShipping::query()->create($shippingArray);
            }

            $this->recordPromotionUsage($order, $promotionDiscounts, $subtotal, $discountSource);
            $this->redeemCoupon($couponModel, $customer, $order, $discountSource, $discount);

            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => 'korapay',
                'status' => 'pending',
                'provider_reference' => $this->buildPaymentReference($order),
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'paid_at' => null,
                'meta' => [
                    'type' => 'checkout_pending',
                    'payment_method' => $validated['payment_method'] ?? 'korapay',
                    'coupon_code' => $coupon['code'] ?? null,
                    'tax_included' => $taxIncluded,
                ],
            ]);

            event(new OrderPlaced($order));

            return [$order, $payment];
        });

        $cart->emptyCart();

        return $this->created(new CheckoutConfirmResource([
            'order_number' => $order->number,
            'payment_reference' => $payment->provider_reference,
        ]));
    }

    private function resolveCart(Request $request): Cart
    {
        $customer = $request->user();
        $cart = Cart::query()
            ->when($customer, fn ($query) => $query->where('user_id', $customer->id))
            ->with(['items', 'shippings'])
            ->first();

        if (! $cart) {
            $cart = Cart::query()->create([
                'user_id' => $customer?->id,
                'session_id' => session()->id(),
            ]);
        }

        return $cart->loadMissing(['items.product.images', 'items.variant', 'shippings']);
    }

    private function buildPricingPayload(Cart $cart, ?Customer $customer): array
    {
        $cartItems = $cart->items;
        $subtotal = $cart->subTotal();
        $shipping = $cart->calculateShippingFees();
        $settings = SiteSetting::query()->first();

        $coupon = session('cart_coupon');
        $discounts = $this->calculateDiscounts($cartItems, $coupon, $customer, $subtotal);
        $discount = $discounts['amount'] ?? 0.0;
        $couponModel = $discounts['coupon_model'] ?? null;

        $shippingTotal = $this->applyShippingRules($shipping, $subtotal, $discount, $settings);
        $taxTotal = $this->calculateTax(max(0, $subtotal - $discount), $settings);
        $taxIncluded = (bool) ($settings?->tax_included ?? false);
        $total = $subtotal + $shippingTotal - $discount + ($taxIncluded ? 0 : $taxTotal);

        $promotionEngine = app(PromotionEngine::class);
        $cartPayload = \App\Http\Resources\User\CartResource::collection($cartItems)->jsonSerialize();
        $cartContext = [
            'lines' => $cartPayload,
            'subtotal' => $subtotal,
            'user_id' => $customer?->id,
        ];
        $promotionModels = $promotionEngine->getApplicablePromotions($cartContext);

        $locale = app()->getLocale();
        $appliedPromotions = $promotionModels->map(function ($promo) use ($locale) {
            return [
                'id' => $promo->id,
                'name' => $promo->localizedValue('name', $locale) ?? $promo->name,
                'description' => $promo->localizedValue('description', $locale) ?? $promo->description,
                'type' => $promo->type,
                'value_type' => $promo->value_type,
                'value' => $promo->value,
                'start_at' => $promo->start_at,
                'end_at' => $promo->end_at,
                'targets' => $promo->targets,
                'conditions' => $promo->conditions,
            ];
        })->values()->all();

        $minimumRequirement = app(CartMinimumService::class)->evaluate($subtotal, $discount, $promotionModels, $couponModel);

        return [
            'subtotal' => $subtotal,
            'shipping' => $shippingTotal,
            'discount' => $discount,
            'tax' => $taxTotal,
            'total' => $total,
            'currency' => $cartItems->first()?->variant?->currency
                ?? $cartItems->first()?->product?->currency
                ?? 'USD',
            'applied_promotions' => $appliedPromotions,
            'minimum_cart_requirement' => $minimumRequirement,
            'coupon' => $discounts['coupon'] ?? null,
            'coupon_model' => $couponModel,
            'promotion_discounts' => $discounts['promotion_discounts'] ?? [],
            'discount_source' => $discounts['source'] ?? null,
        ];
    }

    private function calculateDiscounts($cartItems, ?array $coupon, ?Customer $customer, float $subtotal): array
    {
        $couponValidator = app(CouponValidator::class);
        $couponModel = $couponValidator->resolveFromSession($coupon);
        if ($couponModel) {
            $error = $couponValidator->validateForCart($couponModel, $cartItems, $subtotal, $customer);
            if ($error) {
                session()->forget('cart_coupon');
                $couponModel = null;
                $coupon = null;
            }
        }

        $couponDiscount = $couponModel ? $couponValidator->calculateDiscount($couponModel, $subtotal) : 0.0;
        $cartPayload = \App\Http\Resources\User\CartResource::collection($cartItems)->jsonSerialize();
        $campaign = app(CampaignManager::class)->bestForCart($cartPayload, $subtotal, $customer);

        if ($couponDiscount >= ($campaign['amount'] ?? 0)) {
            return [
                'amount' => $couponDiscount,
                'label' => $couponModel ? __('Coupon: :code', ['code' => $couponModel->code]) : null,
                'source' => $couponModel ? 'coupon' : null,
                'coupon' => $couponModel ? $this->serializeCoupon($couponModel) : null,
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

    private function applyShippingRules(float $shippingTotal, float $subtotal, float $discount, ?SiteSetting $settings): float
    {
        $eligibleTotal = max(0, $subtotal - $discount);
        $threshold = (float) ($settings?->free_shipping_threshold ?? 0);
        $handlingFee = (float) ($settings?->shipping_handling_fee ?? 0);

        if ($threshold > 0 && $eligibleTotal >= $threshold) {
            return 0.0;
        }

        if ($handlingFee > 0 && $shippingTotal > 0) {
            return round($shippingTotal + $handlingFee, 2);
        }

        return $shippingTotal;
    }

    private function calculateTax(float $taxableAmount, ?SiteSetting $settings): float
    {
        if (! $settings || ! $settings->tax_rate) {
            return 0.0;
        }

        return round($taxableAmount * ((float) $settings->tax_rate / 100), 2);
    }

    private function serializeCoupon(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'amount' => $coupon->amount,
            'min_order_total' => $coupon->min_order_total,
            'description' => $coupon->localizedValue('description', app()->getLocale()) ?? $coupon->description,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $promotionDiscounts
     */
    private function buildDiscountSnapshot(
        float $discountAmount,
        ?string $label,
        ?string $source,
        ?array $coupon,
        array $promotionDiscounts,
        string $currency
    ): array {
        return [
            'source' => $source,
            'label' => $label,
            'discount_total' => $discountAmount,
            'currency' => $currency,
            'coupon' => $coupon,
            'promotion_discounts' => array_values($promotionDiscounts),
            'computed_at' => now()->toIso8601String(),
        ];
    }

    private function recordPromotionUsage(Order $order, array $promotionDiscounts, float $subtotal, ?string $campaignSource): void
    {
        if (empty($promotionDiscounts)) {
            return;
        }

        $now = now();
        foreach ($promotionDiscounts as $discount) {
            if (empty($discount['promotion_id'])) {
                continue;
            }
            PromotionUsage::create([
                'promotion_id' => $discount['promotion_id'],
                'user_id' => null,
                'order_id' => $order->id,
                'discount_amount' => $discount['amount'] ?? null,
                'used_at' => $now,
                'meta' => [
                    'promotion_intent' => $discount['intent'] ?? null,
                    'pre_discount_subtotal' => $subtotal,
                    'discount_breakdown' => $promotionDiscounts,
                    'chosen_campaign_source' => $campaignSource,
                ],
            ]);
        }

        $intents = collect($promotionDiscounts)->pluck('intent')->filter()->unique()->values();
        $intentLabels = $intents->map(function ($intent) {
            return match ($intent) {
                'shipping_support' => 'Logistics support applied',
                'cart_growth' => 'Cart growth discount applied',
                'urgency' => 'Flash deal applied',
                'acquisition' => 'Acquisition offer applied',
                default => 'Promotion applied',
            };
        })->unique()->values()->all();

        OrderAuditLog::create([
            'order_id' => $order->id,
            'user_id' => null,
            'action' => 'promotion_applied',
            'note' => $intentLabels ? implode(' | ', $intentLabels) : 'Promotions applied during checkout',
            'payload' => [
                'discounts' => $promotionDiscounts,
                'pre_discount_subtotal' => $subtotal,
                'chosen_campaign_source' => $campaignSource,
            ],
        ]);
    }

    private function redeemCoupon(?Coupon $coupon, ?Customer $customer, Order $order, ?string $discountSource, float $discountAmount): void
    {
        if (! $coupon || $discountSource !== 'coupon' || $discountAmount <= 0) {
            return;
        }

        $coupon->increment('uses');

        if (! $customer) {
            return;
        }

        CouponRedemption::updateOrCreate(
            ['coupon_id' => $coupon->id, 'customer_id' => $customer->id],
            [
                'order_id' => $order->id,
                'status' => 'redeemed',
                'redeemed_at' => now(),
            ]
        );
    }

    private function buildPaymentReference(Order $order): string
    {
        return 'krp_' . strtolower($order->number) . '_' . strtolower(Str::random(6));
    }
}
