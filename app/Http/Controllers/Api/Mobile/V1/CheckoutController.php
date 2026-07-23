<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Domain\Common\Models\Address;
use App\Domain\Orders\Models\OrderAuditLog;
use App\Domain\Products\Models\ProductVariant;
use App\Domain\Products\Services\AliExpressProductImportService;
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
use App\Services\Currency\CurrencyConversionService;
use App\Services\Promotions\PromotionEngine;
use App\Services\User\UserPreferenceService;
use App\Support\ResolvesStorefrontVariantLabels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends ApiController
{
    use ResolvesStorefrontVariantLabels;

    public function preview(PreviewRequest $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $cartItems = $this->resolveCheckoutItems($cart, $request);
        if ($message = $this->validateAliExpressStockForCheckoutItems($cartItems, (string) $request->input('country', 'CN'))) {
            return $this->error($message, 422);
        }
        $payload = $this->buildPricingPayload($cart, $request->user(), $cartItems);

        return $this->success(new CheckoutPreviewResource($payload));
    }

    public function confirm(ConfirmRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $cart = $this->resolveCart($request);
        $cartItems = $this->resolveCheckoutItems($cart, $request);

        if ($cartItems->isEmpty()) {
            return $this->error('Cart is empty', 422);
        }

        if ($message = $this->validateAliExpressStockForCheckoutItems($cartItems, (string) ($validated['country'] ?? 'CN'))) {
            return $this->error($message, 422);
        }

        $customer = $request->user();
        $locale = app()->getLocale();

        $pricing = $this->buildPricingPayload($cart, $customer, $cartItems);
        if ((bool) ($pricing['shipping_unavailable'] ?? false)) {
            return $this->error((string) ($pricing['shipping_unavailable_reason'] ?? 'Shipping is unavailable for one or more items in your cart.'), 422);
        }
        $subtotal = (float) $pricing['subtotal'];
        $shipping = (float) $pricing['shipping'];
        $discount = (float) $pricing['discount'];
        $taxTotal = (float) $pricing['tax'];
        $total = (float) $pricing['total'];
        $coupon = $pricing['coupon'] ?? null;
        $couponModel = $pricing['coupon_model'] ?? null;
        $promotionDiscounts = $pricing['promotion_discounts'] ?? [];
        $discountSource = $pricing['discount_source'] ?? null;
        $shippingLines = $pricing['shipping_lines'] ?? [];

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
            (string) ($pricing['currency'] ?? 'XOF')
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
            $pricing,
            $shippingLines,
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

            $order = Order::createWithGeneratedNumber([
                // user_id references internal users; storefront/mobile customers should only set customer_id.
                'user_id' => null,
                'customer_id' => $customer?->id,
                'guest_name' => null,
                'guest_phone' => null,
                'is_guest' => false,
                'email' => $validated['email'],
                'locale' => $locale,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'currency' => (string) ($pricing['currency'] ?? 'XOF'),
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
            $currencyConverter = app(CurrencyConversionService::class);
            $userCurrency = (string) ($pricing['currency'] ?? 'XOF');

            foreach ($cartItems as $line) {
                $providerId = $line['fulfillment_provider_id'] ?? $fallbackProvider;
                $supplierProduct = \App\Domain\Products\Models\SupplierProduct::query()
                    ->where('product_variant_id', $line['variant_id'])
                    ->when($providerId, fn ($query) => $query->where('fulfillment_provider_id', $providerId))
                    ->first();

                $lineCurrency = $this->resolveCheckoutCurrencyForItem($line);
                $unitPrice = $line->getSinglePrice();
                try {
                    $unitPriceInUserCurrency = $currencyConverter->convertAmount($unitPrice, $lineCurrency, $userCurrency);
                    if ($unitPriceInUserCurrency === null) {
                        \Log::warning('Currency conversion returned null in mobile checkout', [
                            'source_price' => $unitPrice,
                            'source_currency' => $lineCurrency,
                            'target_currency' => $userCurrency,
                            'order_id' => $order->id,
                        ]);
                        $unitPriceInUserCurrency = $unitPrice;
                    }
                } catch (\Throwable $e) {
                    \Log::error('Currency conversion failed in mobile checkout', [
                        'source_price' => $unitPrice,
                        'source_currency' => $lineCurrency,
                        'target_currency' => $userCurrency,
                        'error' => $e->getMessage(),
                        'order_id' => $order->id,
                    ]);
                    $unitPriceInUserCurrency = $unitPrice;
                }
                $totalInUserCurrency = $unitPriceInUserCurrency * $line['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $line['variant_id'],
                    'fulfillment_provider_id' => $providerId,
                    'supplier_product_id' => $supplierProduct?->id,
                    'fulfillment_status' => 'pending',
                    'quantity' => $line['quantity'],
                    'unit_price' => $unitPriceInUserCurrency,
                    'total' => $totalInUserCurrency,
                    'source_sku' => $supplierProduct?->external_sku ?? $line->variant?->sku,
                    'snapshot' => [
                        'name' => $line?->product['name'],
                        'variant' => $line->variant
                            ? $this->resolveVariantDisplayTitle($line->variant, $line->variant->title, $line?->product?->name)
                            : null,
                        'supplier_type' => $line->product?->supplier_type,
                    ],
                    'meta' => [
                        'media' => $line['media'] ?? null,
                        'coupon_code' => $coupon['code'] ?? null,
                        'supplier_type' => $line->product?->supplier_type,
                        'supplier_product_id' => $supplierProduct?->id,
                        'external_product_id' => $supplierProduct?->external_product_id,
                        'external_sku' => $supplierProduct?->external_sku,
                    ],
                ]);
            }

            foreach ($shippingLines as $shippingEntry) {
                OrderShipping::query()->create([
                    'order_id' => $order->id,
                    'fulfillment_provider_id' => $shippingEntry['fulfillment_provider_id'] ?? null,
                    'name' => $shippingEntry['logistic_name'] ?? 'Shipping',
                    'price' => $shippingEntry['logistic_price'] ?? 0,
                    'logistic_name' => $shippingEntry['logistic_name'] ?? null,
                    'logistic_price' => $shippingEntry['logistic_price'] ?? 0,
                    'total_postage_fee' => $shippingEntry['total_postage_fee'] ?? ($shippingEntry['logistic_price'] ?? 0),
                    'aging' => $shippingEntry['aging'] ?? null,
                ]);
            }

            app(\App\Domain\Orders\Services\OrderCostBreakdownService::class)->recalculate($order);

            $this->recordPromotionUsage($order, $promotionDiscounts, $subtotal, $discountSource);
            $this->redeemCoupon($couponModel, $customer, $order, $discountSource, $discount);
            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => 'paystack',
                'status' => 'pending',
                'provider_reference' => null,
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'paid_at' => null,
                'meta' => [
                    'type' => 'checkout_pending',
                    'payment_method' => $validated['payment_method'] ?? 'card',
                    'coupon_code' => $coupon['code'] ?? null,
                    'tax_included' => $taxIncluded,
                ],
            ]);

            return [$order, $payment];
        });

        $selectedItemIds = $cartItems->modelKeys();
        $cart->items()->whereIn('id', $selectedItemIds)->delete();
        $cart->unsetRelation('items');
        $remainingItems = $cart->items()->with(['product.images', 'variant'])->get();
        if ($remainingItems->isEmpty()) {
            $cart->shippings()->delete();
            $cart->delete();
        } else {
            $cart->setRelation('items', $remainingItems);
            $cart->calculateShippingFees();
        }

        return $this->created(new CheckoutConfirmResource([
            'order_number' => $order->number,
            'payment_reference' => $payment->provider_reference,
        ]));
    }

    private function resolveCart(Request $request): Cart
    {
        $customer = $request->user();
        if (! $customer) {
            abort(response()->json(['message' => 'You must log in first to continue shopping.'], 401));
        }

        $cart = Cart::query()
            ->where('user_id', $customer->id)
            ->orderByDesc('updated_at')
            ->first();

        if (! $cart) {
            $cart = Cart::query()->create([
                'user_id' => $customer->id,
            ]);
        }

        return $cart->loadMissing(['items.product.images', 'items.variant', 'shippings']);
    }

    private function resolveCheckoutItems(Cart $cart, Request $request)
    {
        $selectedProductIds = collect($request->input('product_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedProductIds->isEmpty()) {
            return $cart->items;
        }

        return $cart->items
            ->whereIn('product_id', $selectedProductIds->all())
            ->values();
    }

    private function validateAliExpressStockForCheckoutItems($cartItems, string $shipToCountry = 'CN'): ?string
    {
        $cartItems->loadMissing(['product', 'variant.product']);
        $service = app(AliExpressProductImportService::class);
        $shipToCountry = strtoupper(trim($shipToCountry)) !== '' ? strtoupper(trim($shipToCountry)) : 'CN';

        foreach ($cartItems as $item) {
            $product = $item->product;
            $variant = $item->variant;

            if (! $variant instanceof ProductVariant) {
                continue;
            }

            $supplierType = (string) ($product?->supplier_type ?? '');
            $metadata = is_array($variant->metadata ?? null) ? $variant->metadata : [];
            if ($supplierType !== 'aliexpress' && empty($metadata['ali_sku_id'])) {
                continue;
            }

            try {
                $liveStock = $service->refreshVariantLiveStock($variant, [
                    'ship_to_country' => $shipToCountry,
                ]);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            if ($liveStock === null) {
                continue;
            }

            if ((int) $item->quantity > $liveStock) {
                $variantTitle = $this->resolveVariantDisplayTitle($variant, $variant->title, $product?->name);

                return $liveStock > 0
                    ? "AliExpress stock changed for {$product?->name} ({$variantTitle}). Only {$liveStock} left."
                    : "AliExpress stock changed for {$product?->name} ({$variantTitle}). This variant is now out of stock.";
            }

            if ((int) ($item->stock_on_hand ?? -1) !== $liveStock) {
                $item->forceFill(['stock_on_hand' => $liveStock])->save();
            }
        }

        return null;
    }

    private function buildPricingPayload(Cart $cart, ?Customer $customer, $cartItems = null): array
    {
        $cartItems = $cartItems ?? $cart->items;

        // Create cache key based on cart state
        $cartItemsHash = md5(serialize($cartItems->map(fn ($item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'quantity' => $item->quantity,
            'price' => $item->getSinglePrice(),
        ])->toArray()));
        $cacheKey = "checkout:pricing:{$cart->id}:{$customer?->id}:{$cartItemsHash}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($cart, $customer, $cartItems) {
            $sourceCurrency = $this->resolveCheckoutCurrency($cartItems);
            $targetCurrency = app(UserPreferenceService::class)->getPreferences()['currency'] ?? 'XOF';
            $currencyConverter = app(CurrencyConversionService::class);

        $subtotal = (float) $cartItems->reduce(
            fn (float $carry, $item) => $carry + ((float) $item->quantity * (float) $item->getSinglePrice()),
            0.0
        );
        $coupon = $cart->applied_coupon_data ?? session('cart_coupon');
        $discounts = $this->calculateDiscounts($cartItems, $coupon, $customer, $subtotal, $cart);
        $discount = (float) ($discounts['amount'] ?? 0);
        $couponValidator = app(CouponValidator::class);
        $couponModel = $couponValidator->resolveFromSession($coupon);
        $shippingQuote = $cart->quoteShippingForItems($cartItems);
        $settings = SiteSetting::query()->first();
        $shippingTotal = $this->applyShippingRules((float) ($shippingQuote['total'] ?? 0), $subtotal, $discount, $settings);
        $taxTotal = $this->calculateTax(max(0, $subtotal - $discount), $settings);
        $taxIncluded = (bool) ($settings?->tax_included ?? false);
        $total = $subtotal + $shippingTotal - $discount + ($taxIncluded ? 0 : $taxTotal);
        $cartPayload = \App\Http\Resources\User\CartResource::collection($cartItems)->jsonSerialize();
        $promotionModels = app(PromotionEngine::class)->getApplicablePromotions([
            'lines' => $cartPayload,
            'subtotal' => $subtotal,
            'user_id' => $customer?->id,
        ]);
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
        $minimumRequirement = app(CartMinimumService::class)->evaluate(
            $subtotal,
            $discount,
            $shippingTotal,
            $promotionModels,
            $couponModel
        );
        $subtotal = $this->convertCheckoutAmount($currencyConverter, $subtotal, $sourceCurrency, $targetCurrency);
        $shippingTotal = $this->convertCheckoutAmount($currencyConverter, $shippingTotal, $sourceCurrency, $targetCurrency);
        $discount = $this->convertCheckoutAmount($currencyConverter, $discount, $sourceCurrency, $targetCurrency);
        $taxTotal = $this->convertCheckoutAmount($currencyConverter, $taxTotal, $sourceCurrency, $targetCurrency);
        $total = $this->convertCheckoutAmount($currencyConverter, $total, $sourceCurrency, $targetCurrency);
        $shippingLines = collect($shippingQuote['lines'] ?? [])->map(function (array $line) use ($currencyConverter, $sourceCurrency, $targetCurrency) {
            $line['logistic_price'] = $this->convertCheckoutAmount(
                $currencyConverter,
                (float) ($line['logistic_price'] ?? 0),
                $sourceCurrency,
                $targetCurrency
            );
            $line['total_postage_fee'] = $this->convertCheckoutAmount(
                $currencyConverter,
                (float) ($line['total_postage_fee'] ?? ($line['logistic_price'] ?? 0)),
                $sourceCurrency,
                $targetCurrency
            );

            return $line;
        })->values()->all();

        return [
            'subtotal' => $subtotal,
            'shipping' => $shippingTotal,
            'shipping_lines' => $shippingLines,
            'shipping_unavailable' => (bool) ($shippingQuote['unavailable'] ?? false),
            'shipping_unavailable_reason' => $shippingQuote['reason'] ?? null,
            'discount' => $discount,
            'tax' => $taxTotal,
            'total' => $total,
            'currency' => $targetCurrency,
            'applied_promotions' => $appliedPromotions,
            'minimum_cart_requirement' => $minimumRequirement,
            'coupon' => $discounts['coupon'] ?? null,
            'coupon_model' => $couponModel,
            'promotion_discounts' => $discounts['promotion_discounts'] ?? [],
            'discount_source' => $discounts['source'] ?? null,
            'label' => $discounts['label'] ?? null,
        ];
        });
    }

    private function calculateDiscounts($cartItems, ?array $coupon, ?Customer $customer, float $subtotal, ?Cart $cart = null): array
    {
        $couponValidator = app(CouponValidator::class);
        $couponModel = $couponValidator->resolveFromSession($coupon);
        if ($couponModel) {
            $error = $couponValidator->validateForCart($couponModel, $cartItems, $subtotal, $customer);
            if ($error) {
                $cart?->update(['applied_coupon_code' => null, 'applied_coupon_data' => null]);
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

    private function resolveCheckoutCurrency($cartItems): string
    {
        return (string) (
            $cartItems->first()?->variant?->currency
            ?? $cartItems->first()?->product?->currency
            ?? config('currency.base', 'USD')
        );
    }

    private function resolveCheckoutCurrencyForItem($item): string
    {
        return (string) (
            $item?->variant?->currency
            ?? $item?->product?->currency
            ?? config('currency.base', 'USD')
        );
    }

    private function convertCheckoutAmount(
        CurrencyConversionService $currencyConverter,
        float $amount,
        string $sourceCurrency,
        string $targetCurrency
    ): float {
        return $currencyConverter->convertAmount($amount, $sourceCurrency, $targetCurrency) ?? $amount;
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

}
