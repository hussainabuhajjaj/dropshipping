<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Domain\Products\Services\AliExpressProductImportService;
use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
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
use App\Contracts\Cart\CartManagerContract;
use App\Services\CartMinimumService;
use App\Services\Coupons\CouponValidator;
use App\Services\Promotions\PromotionEngine;
use App\Services\Api\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CartController extends ApiController
{
    public function __construct(
        private readonly CartManagerContract $cartIdentityService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);

        return $this->success(new MobileCartResource($this->buildCartPayload($cart, $request)));
    }

    public function store(AddItemRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Ensure quantity is properly set with fallback
        $incomingQty = (int) ($data['quantity'] ?? 1);

        $product = Product::query()
            ->where('is_active', true)
            ->with(['images', 'variants', 'defaultFulfillmentProvider'])
            ->findOrFail($data['product_id']);

        $variant = null;
        if (! empty($data['variant_id'])) {
            $variant = $product->variants->firstWhere('id', (int) $data['variant_id']);
            if (! $variant) {
                return $this->error('Selected variant is invalid for this product.', 422);
            }
        }
        $selectedVariant = $variant ?? $product->variants->first();
        $providerId = (int) ($product->default_fulfillment_provider_id ?? 0);
        if ($providerId === 1) {
            if (! $selectedVariant) {
                return $this->error('Selected variant is no longer available. Please choose another variant.', 422);
            }

            $meta = is_array($selectedVariant->metadata ?? null) ? $selectedVariant->metadata : [];
            if (empty($meta['cj_vid'])) {
                return $this->error('Selected variant is invalid for fulfillment. Please choose another variant.', 422);
            }
        }
        if ($selectedVariant && ! $this->isVariantAvailableForCart($selectedVariant, $product, $providerId, $incomingQty)) {
            return $this->error('Selected variant is no longer available. Please choose another variant.', 422);
        }

        $cart = $this->resolveCart($request);
        $items = $cart->items()->with(['product', 'variant'])->get();

        $existing = $items
            ->where('product_id', $product->id)
            ->when(isset($variant), fn ($collection) => $collection->where('variant_id', $variant->id))
            ->first();

        if ($existing) {
            $newQty = $existing->quantity + $incomingQty;
            if (! $this->hasStock($existing->toArray(), $newQty, $variant)) {
                return $this->error('Insufficient stock for this item.', 422);
            }

            $existing->update(['quantity' => $newQty]);
        } else {
            $line = $this->buildLine($cart, $product, $selectedVariant, $incomingQty);
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

        $providerId = (int) ($cartItem->fulfillment_provider_id ?? $cartItem->product?->default_fulfillment_provider_id ?? 0);
        if (! $this->hasStock($cartItem->toArray(), $newQty, $variant)
            || ($variant && ! $this->isVariantAvailableForCart($variant, $cartItem->product, $providerId, $newQty))) {
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

        $couponData = [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'amount' => $coupon->amount,
            'min_order_total' => $coupon->min_order_total,
            'description' => $coupon->localizedValue('description', app()->getLocale()) ?? $coupon->description,
        ];

        $cart->update([
            'applied_coupon_code' => $coupon->code,
            'applied_coupon_data' => $couponData,
        ]);

        return $this->success(new MobileCartResource($this->buildCartPayload($cart, $request)));
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $cart->update(['applied_coupon_code' => null, 'applied_coupon_data' => null]);

        return $this->success(new MobileCartResource($this->buildCartPayload($cart, $request)));
    }

    private function resolveCart(Request $request): Cart
    {
        $cart = $this->cartIdentityService->resolveCart($request, $request->user(), true);

        return ($cart ?? Cart::createCart())->loadMissing(['items.product.images', 'items.variant']);
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
        $summary = $cart->getSummery();
        $customer = $request->user();

        return [
            'lines' => $cartItems,
            'guest_token' => $this->cartIdentityService->guestTokenForRequest($request, $cart),
            'currency' => $summary['currency'] ?? 'USD',
            'subtotal' => (float)($summary['subtotal'] ?? 0),
            'shipping' => (float)($summary['shipping'] ?? 0),
            'discount' => (float)($summary['discount'] ?? 0),
            'tax' => (float)($summary['tax_total'] ?? 0),
            'total' => (float)($summary['total'] ?? 0),
            'coupon' => $summary['coupon'] ?? null,
            'discount_label' => $summary['discount_label'] ?? null,
            'applied_promotions' => $summary['appliedPromotions'] ?? [],
            'minimum_cart_requirement' => $summary['minimum_cart_requirement'] ?? null,
            'customer' => [
                'id' => $customer?->id,
                'name' => $customer?->name,
                'email' => $customer?->email,
                'phone' => $customer?->phone,
            ],
        ];
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
            }
        }

        $couponDiscount = $couponModel ? $couponValidator->calculateDiscount($couponModel, $subtotal) : 0.0;
        $cartPayload = \App\Http\Resources\User\CartResource::collection($cartItems)->jsonSerialize();
        $campaign = app(CampaignManager::class)->bestForCart($cartPayload, $subtotal, $customer);

        if ($couponDiscount >= ($campaign['amount'] ?? 0)) {
            return $this->buildCouponDiscountResponse($couponModel, $couponDiscount);
        }

        return $this->buildCampaignDiscountResponse($campaign);
    }

    private function buildCouponDiscountResponse(?Coupon $couponModel, float $amount): array
    {
        return [
            'amount' => $amount,
            'label' => $couponModel ? __('Coupon: :code', ['code' => $couponModel->code]) : null,
            'source' => $couponModel ? 'coupon' : null,
            'coupon' => $couponModel ? $this->serializeCoupon($couponModel) : null,
            'coupon_model' => $couponModel,
            'promotion_discounts' => [],
        ];
    }

    private function buildCampaignDiscountResponse(array $campaign): array
    {
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
            'description' => $coupon->localizedValue('description', app()->getLocale()) ?? $coupon->description,
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

    private function isVariantAvailableForCart(ProductVariant $variant, ?Product $product, int $providerId, int $desiredQty = 1): bool
    {
        if ($providerId === 1) {
            return $this->checkCjVariantAvailability($variant);
        }

        return $this->checkAliExpressVariantAvailability($variant, $product, $desiredQty);
    }

    private function checkCjVariantAvailability(ProductVariant $variant): bool
    {
        $meta = is_array($variant->metadata ?? null) ? $variant->metadata : [];
        $cjVid = $meta['cj_vid'] ?? null;

        if (!$cjVid) {
            return true;
        }

        try {
            app(CJDropshippingClient::class)->getVariantByVid((string) $cjVid);
            return true;
        } catch (ApiException $e) {
            return $this->handleCjApiException($variant, $cjVid, $e);
        } catch (\Throwable $e) {
            Log::warning('Skipping CJ variant availability check due to runtime error', [
                'variant_id' => $variant->id,
                'cj_vid' => $cjVid,
                'error' => $e->getMessage(),
            ]);
            return true;
        }
    }

    private function handleCjApiException(ProductVariant $variant, ?string $cjVid, ApiException $e): bool
    {
        $message = strtolower($e->getMessage());
        
        if (str_contains($message, 'variant not found') || str_contains($message, 'vid')) {
            Log::warning('Rejected add-to-cart for unavailable CJ variant', [
                'variant_id' => $variant->id,
                'cj_vid' => $cjVid,
                'message' => $e->getMessage(),
            ]);
            return false;
        }

        // Do not block cart on transient CJ API errors
        return true;
    }

    private function checkAliExpressVariantAvailability(ProductVariant $variant, ?Product $product, int $desiredQty): bool
    {
        $supplierType = (string) ($product?->supplier_type ?? '');
        $meta = is_array($variant->metadata ?? null) ? $variant->metadata : [];

        if ($supplierType !== 'aliexpress' && empty($meta['ali_sku_id'])) {
            return true;
        }

        try {
            $liveStock = app(AliExpressProductImportService::class)->refreshVariantLiveStock($variant, [
                'ship_to_country' => 'CN',
            ]);

            if ($liveStock !== null) {
                return $liveStock >= $desiredQty;
            }
        } catch (\Throwable $e) {
            Log::warning('Skipping AliExpress variant availability check due to runtime error', [
                'variant_id' => $variant->id,
                'ali_sku_id' => $meta['ali_sku_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }
}
