<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Domain\Products\Models\ProductVariant;
use App\Http\Requests\Api\Mobile\V1\Cart\AddItemRequest;
use App\Http\Requests\Api\Mobile\V1\Cart\ApplyCouponRequest;
use App\Http\Requests\Api\Mobile\V1\Cart\UpdateItemRequest;
use App\Http\Resources\Mobile\V1\CartResource as MobileCartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\CampaignManager;
use App\Services\CartMinimumService;
use App\Services\Coupons\CouponValidator;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CartController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);

        return $this->success(new MobileCartResource($this->buildCartPayload($cart, $request)));
    }

    public function store(AddItemRequest $request): JsonResponse
    {
        $data = $request->validated();

        $product = Product::query()
            ->where('is_active', true)
            ->with(['images', 'variants', 'defaultFulfillmentProvider'])
            ->findOrFail($data['product_id']);

        $variant = null;
        if (! empty($data['variant_id'])) {
            $variant = $product->variants->firstWhere('id', (int) $data['variant_id']);
        }

        $cart = $this->resolveCart($request);
        $items = $cart->items()->with(['product', 'variant'])->get();

        $existing = $items
            ->where('product_id', $product->id)
            ->when(isset($variant), fn ($collection) => $collection->where('variant_id', $variant->id))
            ->first();

        $incomingQty = (int) ($data['quantity'] ?? 1);

        if ($existing) {
            $newQty = $existing->quantity + $incomingQty;
            if (! $this->hasStock($existing->toArray(), $newQty, $variant)) {
                return $this->error('Insufficient stock for this item.', 422);
            }

            $existing->update(['quantity' => $newQty]);
        } else {
            $line = $this->buildLine($cart, $product, $variant, $incomingQty);
            if (! $this->hasStock($line, $incomingQty, $variant)) {
                return $this->error('Insufficient stock for this item.', 422);
            }
            CartItem::query()->create($line);
        }

        $cart->load(['items.product.images', 'items.variant']);

        return $this->success(new MobileCartResource($this->buildCartPayload($cart, $request)));
    }

    public function update(UpdateItemRequest $request, string $itemId): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $cartItem = $cart->items()->with(['product', 'variant'])->find($itemId);

        if (! $cartItem) {
            return $this->notFound('Cart item not found');
        }

        $newQty = (int) $request->validated()['quantity'];
        $variant = $cartItem->variant;

        if (! $this->hasStock($cartItem->toArray(), $newQty, $variant)) {
            return $this->error('Insufficient stock for this item.', 422);
        }

        $cartItem->update(['quantity' => $newQty]);
        $cart->load(['items.product.images', 'items.variant']);

        return $this->success(new MobileCartResource($this->buildCartPayload($cart, $request)));
    }

    public function destroy(Request $request, string $itemId): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $cartItem = $cart->items()->find($itemId);

        if (! $cartItem) {
            return $this->notFound('Cart item not found');
        }

        $cartItem->delete();
        $cart->load(['items.product.images', 'items.variant']);

        return $this->success(new MobileCartResource($this->buildCartPayload($cart, $request)));
    }

    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        $data = $request->validated();

        $now = Carbon::now();
        $coupon = Coupon::query()
            ->where('code', $data['code'])
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->first();

        if (! $coupon) {
            return $this->error('Coupon not found or inactive.', 404);
        }

        $cart = $this->resolveCart($request);
        $cartItems = $cart->items()->with(['product', 'variant'])->get();
        $subtotal = $cart->subTotal();
        $customer = $request->user();

        $couponValidator = app(CouponValidator::class);
        $error = $couponValidator->validateForCart($coupon, $cartItems, $subtotal, $customer);
        if ($error) {
            return $this->error($error, 422);
        }

        session(['cart_coupon' => [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'amount' => $coupon->amount,
            'min_order_total' => $coupon->min_order_total,
            'description' => $coupon->description,
        ]]);

        return $this->success(new MobileCartResource($this->buildCartPayload($cart, $request)));
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        session()->forget('cart_coupon');

        $cart = $this->resolveCart($request);

        return $this->success(new MobileCartResource($this->buildCartPayload($cart, $request)));
    }

    private function resolveCart(Request $request): Cart
    {
        $customer = $request->user();
        $cart = Cart::query()
            ->when($customer, fn ($query) => $query->where('user_id', $customer->id))
            ->first();

        if (! $cart) {
            $cart = Cart::query()->create([
                'user_id' => $customer?->id,
                'session_id' => session()->id(),
            ]);
        }

        return $cart->loadMissing(['items.product.images', 'items.variant']);
    }

    private function buildLine(Cart $cart, Product $product, ?ProductVariant $variant, int $quantity): array
    {
        $selectedVariant = $variant ?? $product->variants->first();

        return [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $selectedVariant?->id,
            'fulfillment_provider_id' => $product->default_fulfillment_provider_id,
            'quantity' => $quantity,
            'stock_on_hand' => $selectedVariant?->stock_on_hand ?? $product->stock_on_hand,
        ];
    }

    private function buildCartPayload(Cart $cart, Request $request): array
    {
        $cartItems = $cart->items;
        $coupon = session('cart_coupon');
        $subtotal = $cart->subTotal();
        $customer = $request->user();

        $discounts = $this->calculateDiscounts($cartItems, $coupon, $customer, $subtotal);
        $discount = $discounts['amount'] ?? 0.0;
        $couponModel = $discounts['coupon_model'] ?? null;

        $shipping = $cart->calculateShippingFees();
        $settings = SiteSetting::query()->first();
        $taxTotal = $this->calculateTax(max(0, $subtotal - $discount), $settings);
        $total = $subtotal + $shipping - $discount + $taxTotal;

        $promotionEngine = app(PromotionEngine::class);
        $cartPayload = \App\Http\Resources\User\CartResource::collection($cartItems)->jsonSerialize();
        $cartContext = [
            'lines' => $cartPayload,
            'subtotal' => $subtotal,
            'user_id' => $customer?->id,
        ];
        $promotionModels = $promotionEngine->getApplicablePromotions($cartContext);
        $appliedPromotions = $promotionModels->map(function ($promo) {
            return [
                'id' => $promo->id,
                'name' => $promo->name,
                'description' => $promo->description,
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
        $firstItem = $cartItems->first();
        $currency = $firstItem?->variant?->currency
            ?? $firstItem?->product?->currency
            ?? 'USD';

        return [
            'lines' => $cartItems,
            'currency' => $currency,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'discount' => $discount,
            'tax' => $taxTotal,
            'total' => $total,
            'coupon' => $discounts['coupon'] ?? null,
            'discount_label' => $discounts['label'] ?? null,
            'applied_promotions' => $appliedPromotions,
            'minimum_cart_requirement' => $minimumRequirement,
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
                'label' => $couponModel ? ('Coupon: ' . $couponModel->code) : null,
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

    private function serializeCoupon(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'amount' => $coupon->amount,
            'min_order_total' => $coupon->min_order_total,
            'description' => $coupon->description,
        ];
    }

    private function calculateTax(float $taxableAmount, ?SiteSetting $settings): float
    {
        if (! $settings || ! $settings->tax_rate) {
            return 0.0;
        }

        return round($taxableAmount * ((float) $settings->tax_rate / 100), 2);
    }

    private function hasStock(array $line, int $desiredQty, ?ProductVariant $variant = null): bool
    {
        if (array_key_exists('stock_on_hand', $line) && is_numeric($line['stock_on_hand'])) {
            $available = (int) $line['stock_on_hand'];
            return $available >= $desiredQty;
        }

        if ($variant && $variant->stock_on_hand !== null) {
            return $variant->stock_on_hand >= $desiredQty;
        }

        // No live CJ check from mobile; allow if no stock data.
        return true;
    }
}
