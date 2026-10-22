<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use App\Services\AbandonedCartService;
use App\Services\CampaignManager;
use App\Services\Cart\CartIdentityService;
use App\Services\CartMinimumService;
use App\Domain\Affiliates\Services\AffiliateReferralDiscountService;
use App\Services\Coupons\CouponValidator;
use App\Services\Promotions\PromotionEngine;
use App\Services\Promotions\PromotionHomepageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Carbon;

class CartController extends Controller
{
    public function __construct(
        private readonly CartIdentityService $cartIdentityService,
    ) {
    }

    public function index(): Response
    {
        $cart = $this->getCart();
        $cartItems = $this->cart();
        
        app(AffiliateReferralDiscountService::class)->autoApplyReferralCoupon();

        $customer = auth('customer')->user();
        $cartPayload = CartResource::collection($cartItems)->jsonSerialize();
        
        [$coupon, $couponModel, $discount, $discountLabel] = $this->calculateDiscounts($cartItems, $cartPayload, $customer);
        [$campaignDiscount, $campaignLabel] = $this->calculateCampaignDiscount($cartPayload, $customer);

        $finalDiscount = $discount >= $campaignDiscount ? $discount : $campaignDiscount;
        $finalLabel = $discount >= $campaignDiscount ? $discountLabel : $campaignLabel;

        $shipping = $cart->calculateShippingFees();
        $subtotal = $cart->subTotal();
        $itemCount = collect($cartPayload)->sum(fn ($line) => (int) ($line['quantity'] ?? 0));
        $estimatedTotal = max(0, round($subtotal - $finalDiscount + $shipping, 2));
        $savingsTotal = $this->calculateSavingsTotal($cartPayload, $finalDiscount);

        $appliedPromotions = $this->getAppliedPromotions($cartPayload, $subtotal, $customer);
        $cartPromotions = $this->getCartPromotions($cartItems);
        
        $promotionEngine = app(PromotionEngine::class);
        $promotionModels = $promotionEngine->getApplicablePromotions([
            'lines' => $cartPayload,
            'subtotal' => $subtotal,
            'user_id' => $customer?->id,
        ]);
        
        $minimumRequirement = app(CartMinimumService::class)->evaluate(
            $subtotal, 
            $finalDiscount, 
            $shipping, 
            $promotionModels, 
            $couponModel
        );

        $currency = $cartItems->first()?->variant?->currency 
            ?? $cartItems->first()?->product?->currency 
            ?? 'USD';

        return Inertia::render('Cart/Index', [
            'lines' => $cartPayload,
            'currency' => $currency,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'discount' => $finalDiscount,
            'estimated_total' => $estimatedTotal,
            'item_count' => $itemCount,
            'savings_total' => $savingsTotal,
            'discount_label' => $finalLabel,
            'coupon' => $coupon,
            'appliedPromotions' => $appliedPromotions,
            'cartPromotions' => $cartPromotions,
            'minimum_cart_requirement' => $minimumRequirement,
        ]);
    }

    private function calculateDiscounts($cartItems, array $cartPayload, $customer): array
    {
        $coupon = session('cart_coupon');
        $subtotal = collect($cartPayload)->sum(fn ($line) => (float) ($line['price'] ?? 0) * (int) ($line['quantity'] ?? 0));
        
        $couponValidator = app(CouponValidator::class);
        $couponModel = $couponValidator->resolveFromSession($coupon);
        
        if (!$couponModel) {
            return [null, null, 0.0, null];
        }

        $error = $couponValidator->validateForCart($couponModel, $cartItems, $subtotal, $customer);
        if ($error) {
            session()->forget('cart_coupon');
            return [null, null, 0.0, null];
        }

        $discount = $couponValidator->calculateDiscount($couponModel, $subtotal);
        $label = __('Coupon: :code', ['code' => (string) ($coupon['code'] ?? '')]);
        
        return [$coupon, $couponModel, $discount, $label];
    }

    private function calculateCampaignDiscount(array $cartPayload, $customer): array
    {
        $subtotal = collect($cartPayload)->sum(fn ($line) => (float) ($line['price'] ?? 0) * (int) ($line['quantity'] ?? 0));
        $campaign = app(CampaignManager::class)->bestForCart($cartPayload, $subtotal, $customer);
        
        return [$campaign['amount'] ?? 0.0, $campaign['label'] ?? null];
    }

    private function calculateSavingsTotal(array $cartPayload, float $discount): float
    {
        $compareAtSavings = collect($cartPayload)->sum(function (array $line) {
            $compareAt = (float) ($line['compare_at_price'] ?? 0);
            $price = (float) ($line['price'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($compareAt <= $price || $quantity <= 0) {
                return 0;
            }

            return ($compareAt - $price) * $quantity;
        });

        return max(0, round($compareAtSavings + $discount, 2));
    }

    private function getAppliedPromotions(array $cartPayload, float $subtotal, $customer): array
    {
        $promotionEngine = app(PromotionEngine::class);
        $cartContext = [
            'lines' => $cartPayload,
            'subtotal' => $subtotal,
            'user_id' => $customer?->id,
        ];
        
        $promotionModels = $promotionEngine->getApplicablePromotions($cartContext);
        $locale = app()->getLocale();
        
        return $promotionModels->map(fn ($promo) => [
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
        ])->values()->all();
    }

    private function getCartPromotions($cartItems): array
    {
        $productIds = $cartItems->pluck('product_id')->filter()->unique()->values()->all();
        $categoryIds = $cartItems->map(fn ($line) => $line->product?->category_id)->filter()->unique()->values()->all();
        
        return app(PromotionHomepageService::class)->getPromotionsForPlacement('cart', $productIds, $categoryIds);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = Product::query()
            ->where('is_active', true)
            ->with(['images', 'variants', 'defaultFulfillmentProvider'])
            ->findOrFail($data['product_id']);

        $variant = $this->findVariant($product, $data['variant_id'] ?? null);
        $selectedVariant = $variant ?? $product->variants->first();

        if (!$this->validateCJVariant($product, $selectedVariant, $data['variant_id'] ?? null)) {
            return back()->withErrors(['cart' => 'Selected variant is no longer available. Please choose another variant.']);
        }

        $cart = $this->cart();
        $existing = $this->findExistingCartItem($cart, $product->id, $variant?->id);
        $incomingQty = (int) ($data['quantity'] ?? 1);

        if (!$this->canAddToCart($existing, $incomingQty, $variant)) {
            return back()->withErrors(['cart' => 'Insufficient stock for this item.']);
        }

        if ($existing) {
            $existing->update(['quantity' => $existing['quantity'] + $incomingQty]);
        } else {
            $line = $this->buildLine($product, $selectedVariant, $incomingQty);
            CartItem::query()->create($line);
        }

        $this->captureAbandonedCart($cart);

        return back()->with('cart_notice', 'Added to cart');
    }

    private function findVariant(Product $product, ?int $variantId): ?ProductVariant
    {
        if (!$variantId) {
            return null;
        }

        return $product->variants->firstWhere('id', $variantId);
    }

    private function validateCJVariant(Product $product, ?ProductVariant $selectedVariant, ?int $variantId): bool
    {
        $providerId = (int) ($product->default_fulfillment_provider_id ?? 0);
        
        if ($providerId !== 1) {
            return true;
        }

        if (!$selectedVariant) {
            Log::warning('Cart: no variant for CJ product', [
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'variants_count' => $product->variants->count(),
            ]);
            return false;
        }

        $meta = is_array($selectedVariant->metadata ?? null) ? $selectedVariant->metadata : [];
        if (empty($meta['cj_vid'])) {
            return false;
        }

        return true;
    }

    private function findExistingCartItem($cart, int $productId, ?int $variantId)
    {
        return $cart->where('product_id', $productId)
            ->when($variantId !== null, fn ($query) => $query->where('variant_id', $variantId))
            ->first();
    }

    private function canAddToCart($existing, int $incomingQty, ?ProductVariant $variant): bool
    {
        if ($existing) {
            $newQty = $existing['quantity'] + $incomingQty;
            return $this->hasStock($existing->toArray(), $newQty, $variant);
        }

        $line = ['quantity' => $incomingQty];
        return $this->hasStock($line, $incomingQty, $variant);
    }

    public function destroy(string $lineId): RedirectResponse
    {
        $cart = $this->cart()->where('id', $lineId)->first();
        if (isset($cart)) {
            $cart->delete();
            $this->captureAbandonedCart($cart);
        }
        return back();
    }

    public function update(string $lineId, Request $request): RedirectResponse
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $newQty = (int)$request->input('quantity');

        $cartItems = $this->cart();
        $cart = $cartItems->where('id', $lineId)->first();

        if ($cart) {
            $variant = $cart->variant;
            if (!$this->hasStock($cart->toArray(), $newQty, $variant)) {
                return back()->withErrors(['cart' => 'Insufficient stock for this item.']);
            }

            $cart->update(['quantity' => $newQty]);
        }
        $this->captureAbandonedCart($cart);
        return back();
    }

    public function getCart(): ?Cart
    {
        return $this->cartIdentityService->resolveCart(request(), auth('customer')->user(), true);
    }

    private function cart()
    {
        $cart = $this->getCart();

        return CartItem::query()->where('cart_id', @$cart?->id)
            ->with(['product', 'variant'])
            ->get();
    }


    private function buildLine(Product $product, ?ProductVariant $variant, int $quantity): array
    {
        $cart = $this->getCart();
        $selectedVariant = $variant ?? $product->variants->first();
        return [
            'cart_id' => @$cart->id,
            'product_id' => $product->id,
            'variant_id' => $selectedVariant?->id,
            'fulfillment_provider_id' => $product->default_fulfillment_provider_id,
            'quantity' => $quantity,
            'stock_on_hand' => $selectedVariant?->stock_on_hand ?? $product->stock_on_hand,

//            'id' => Str::uuid()->toString(),
//            'name' => $product->name,
//            'variant' => $selectedVariant?->title,
//            'price' => (float)($selectedVariant?->price ?? $product->selling_price ?? 0),
//            'currency' => $selectedVariant?->currency ?? $product->currency ?? 'USD',
//            'media' => $product->images?->sortBy('position')->pluck('url')->values()->all() ?? [],
//            'sku' => $selectedVariant?->sku,
//            'cj_pid' => $product->attributes['cj_pid'] ?? null,
//            'cj_vid' => $selectedVariant?->metadata['cj_vid'] ?? null,
        ];
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $now = Carbon::now();
        $coupon = Coupon::query()
            ->where('code', $data['code'])
            ->where('is_active', true)
            ->where(fn($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->first();

        if (!$coupon) {
            return back()->withErrors(['code' => 'Coupon not found or inactive.'])->withInput();
        }

        $cart = $this->getCart();
        $cart_items = $this->cart();
        $subtotal = $cart->subTotal();
        $customer = auth('customer')->user();
        $couponValidator = app(CouponValidator::class);
        $error = $couponValidator->validateForCart($coupon, $cart_items, $subtotal, $customer);
        if ($error) {
            return back()->withErrors(['code' => $error])->withInput();
        }

        session(['cart_coupon' => [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'amount' => $coupon->amount,
            'min_order_total' => $coupon->min_order_total,
            'description' => $coupon->localizedValue('description', app()->getLocale()) ?? $coupon->description,
        ]]);

        $this->captureAbandonedCart($this->cart());

        return back()->with('cart_notice', 'Coupon applied.');
    }

    public function removeCoupon(): RedirectResponse
    {
        session()->forget('cart_coupon');
        $this->captureAbandonedCart($this->cart());
        return back()->with('cart_notice', 'Coupon removed.');
    }

    public function abandon(Request $request): JsonResponse
    {
        $cart = $this->cart();
        if ($cart->isEmpty()) {
            return response()->json(['status' => 'ok', 'message' => 'Cart is empty']);
        }

        $email = $request->input('email') ?: Auth::guard('customer')->user()?->email;

        app(AbandonedCartService::class)->capture(
            $cart->toArray(),
            $email,
            Auth::guard('customer')->id()
        );

        return response()->json(['status' => 'ok']);
    }

    private function captureAbandonedCart($cart): void
    {
        if (empty($cart)) {
            return;
        }

        app(AbandonedCartService::class)->capture(
            $cart->toArray(),
            Auth::guard('customer')->user()?->email,
            Auth::guard('customer')->id()
        );
    }

    private function hasCjStock(array $line, int $desiredQty): bool
    {
        // Prefer local stock snapshot if available before hitting CJ APIs
        if (array_key_exists('stock_on_hand', $line) && is_numeric($line['stock_on_hand'])) {
            return (int) $line['stock_on_hand'] >= $desiredQty;
        }

        $identifiers = $this->resolveCjIdentifiers($line);
        
        if (!$identifiers['cj_vid'] && !$identifiers['cj_pid'] && !$identifiers['sku']) {
            return true; // No CJ identifiers found, allow by default
        }

        return $this->checkCjStockAvailability($identifiers, $desiredQty);
    }

    private function resolveCjIdentifiers(array $line): array
    {
        $cjVid = $line['cj_vid'] ?? null;
        $cjPid = $line['cj_pid'] ?? null;
        $sku = $line['sku'] ?? null;

        // If we have identifiers, return them
        if ($cjVid || $cjPid || $sku) {
            return ['cj_vid' => $cjVid, 'cj_pid' => $cjPid, 'sku' => $sku];
        }

        // Try to fetch from variant
        if (isset($line['variant_id'])) {
            $variant = ProductVariant::query()->find($line['variant_id']);
            if ($variant) {
                $cjVid = $variant->metadata['cj_vid'] ?? null;
                $sku = $variant->sku ?? null;
                
                if ($cjVid || $sku) {
                    return ['cj_vid' => $cjVid, 'cj_pid' => null, 'sku' => $sku];
                }
            }
        }

        // Try to fetch from product
        if (isset($line['product_id'])) {
            $product = Product::query()->find($line['product_id']);
            if ($product) {
                $attributes = is_array($product->attributes ?? null) ? $product->attributes : [];
                $cjPid = $attributes['cj_pid'] ?? null;
                
                return ['cj_vid' => null, 'cj_pid' => $cjPid, 'sku' => null];
            }
        }

        return ['cj_vid' => null, 'cj_pid' => null, 'sku' => null];
    }

    private function checkCjStockAvailability(array $identifiers, int $desiredQty): bool
    {
        $client = app(CJDropshippingClient::class);

        try {
            $resp = match (true) {
                (bool) $identifiers['cj_vid'] => $client->getStockByVid((string) $identifiers['cj_vid']),
                (bool) $identifiers['sku'] => $client->getStockBySku((string) $identifiers['sku']),
                (bool) $identifiers['cj_pid'] => $client->getStockByPid((string) $identifiers['cj_pid']),
                default => null,
            };

            if (!$resp) {
                return true;
            }

            return $this->sumStorage($resp->data ?? null) >= $desiredQty;
        } catch (ApiException $exception) {
            Log::warning('CJ stock check failed', [
                'error' => $exception->getMessage(),
                'identifiers' => $identifiers,
            ]);
            return true; // allow on API failure
        } catch (\Throwable $exception) {
            Log::error('CJ stock check failed', [
                'error' => $exception->getMessage(),
                'identifiers' => $identifiers,
            ]);
            return true; // allow on error
        }
    }

    private function sumStorage(mixed $payload): int
    {
        return $this->extractStorageTotal($payload);
    }

    private function extractStorageTotal(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (!is_array($value)) {
            return 0;
        }

        $total = 0;

        // Check for storageNum at current level
        if (array_key_exists('storageNum', $value) && is_numeric($value['storageNum'])) {
            $total += (int) $value['storageNum'];
        }

        // Recursively sum from nested arrays
        foreach ($value as $entry) {
            $total += $this->extractStorageTotal($entry);
        }

        return $total;
    }

    private function hasStock(array $line, int $desiredQty, ?ProductVariant $variant = null): bool
    {
        // Check local stock_on_hand first (variant or product level)
        if (array_key_exists('stock_on_hand', $line) && is_numeric($line['stock_on_hand'])) {
            return (int) $line['stock_on_hand'] >= $desiredQty;
        }

        if ($variant && $variant->stock_on_hand !== null) {
            return $variant->stock_on_hand >= $desiredQty;
        }

        // Fallback to live CJ API check if no local stock data
        return $this->hasCjStock($line, $desiredQty);
    }

}
