<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Affiliates\Services\AffiliateReferralDiscountService;
use App\Domain\Common\Models\Address;
use App\Events\Orders\OrderPlaced;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\CartResource;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderShipping;
use App\Models\Payment;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\GiftCard;
use App\Models\PromotionUsage;
use App\Models\SiteSetting;
use App\Domain\Orders\Models\OrderAuditLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Domain\Payments\PaymentService;
use App\Services\Api\ApiException;
use App\Services\AbandonedCartService;
use App\Services\CampaignManager;
use App\Services\CartMinimumService;
use App\Services\Coupons\CouponValidator;
use App\Services\Promotions\PromotionEngine;
use App\Services\Promotions\PromotionHomepageService;
use App\Support\ResolvesStorefrontVariantLabels;
use App\Models\Product;
use App\Services\ProductRecommendationService;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    use ResolvesStorefrontVariantLabels;

    public function getCartWithItems()
    {
        $customerId = auth('customer')->id();

        $data['cart'] = Cart::query()
            ->where(function ($q) use ($customerId) {
                if ($customerId) {
                    $q->where('user_id', $customerId);
                }
                $q->orWhere('session_id', session()->id());
            })
            ->with('items')
            ->first();

        $data['cart_items'] = $data['cart']?->items;
        if (!$data['cart'] || !$data['cart_items'] || !$data['cart_items']->count()) {
            return redirect()->route('products.index');
        }
        return $data;
    }

    protected function buildCartContext(Collection $cartItems, float $subtotal): array
    {
        return [
            'lines' => (CartResource::collection($cartItems))->jsonSerialize(),
            'subtotal' => $subtotal,
            'user_id' => auth('customer')->id(),
        ];
    }

    public function index(): Response|RedirectResponse
    {
        return redirect()->route('pay.index', ['cart']);
        $result = $this->getCartWithItems();

        if ($result instanceof RedirectResponse) {
            return $result;
        }

        $cart = $result['cart'];
        $cart_items = $result['cart_items'];
        $subtotal = $cart->subTotal();
        $shippingQuote = $cart->quoteShippingForItems($cart_items, true);
        $shipping = (float) ($shippingQuote['total'] ?? 0);

        $customer = auth('customer')->user();

        app(AbandonedCartService::class)->capture($cart_items->toArray(), $customer?->email, $customer?->id);

        $defaultAddress = isset($customer) ? $customer?->addresses()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first() : null;

        $addresses = isset($customer) ? $customer?->addresses()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get() : collect();

        $selectedMethod = 'standard';
        $coupon = session('cart_coupon');
        $discounts = $this->calculateDiscounts($cart, $cart_items, $coupon, $customer, $subtotal);
        $discount = $discounts['amount'];
        $coupon = $discounts['coupon'] ?? null;
        $cartContext = $this->buildCartContext($cart_items, $subtotal);

        $settings = SiteSetting::query()->first();

        $taxTotal = $this->calculateTax(max(0, $subtotal - $discount), $settings);
        $taxIncluded = (bool)($settings?->tax_included ?? false);
        $total = $subtotal + $shipping - $discount + ($taxIncluded ? 0 : $taxTotal);

        $appliedGiftCard = $this->getAppliedGiftCard();
        $giftCardDeduction = $appliedGiftCard ? min($appliedGiftCard['amount'], max(0, $total)) : 0;
        $totalAfterGiftCard = max(0, $total - $giftCardDeduction);

        $promotionEngine = app(PromotionEngine::class);
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

        $productIds = $cart_items->pluck('product_id')->filter()->unique()->values()->all();
        $categoryIds = $cart_items->map(fn($line) => $line->product?->category_id)->filter()->unique()->values()->all();
        $cartPromotions = app(PromotionHomepageService::class)->getPromotionsForPlacement('checkout', $productIds, $categoryIds);
        $minimumRequirement = app(CartMinimumService::class)->evaluate($subtotal, $discount, $shipping, $promotionModels, $coupon);

        return Inertia::render('Checkout/Index', [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'shipping_unavailable' => (bool) ($shippingQuote['unavailable'] ?? false),
            'shipping_unavailable_reason' => $shippingQuote['reason'] ?? null,
            'discount' => $discount,
            'coupon' => $coupon,
            'discount_label' => @$discounts['label'],
            'appliedPromotions' => $appliedPromotions,
            'cartPromotions' => $cartPromotions,
            'minimum_cart_requirement' => $minimumRequirement,
            'tax_total' => $taxTotal,
            'tax_label' => $settings?->tax_label ?? 'Tax',
            'tax_included' => $taxIncluded,
            'total' => $totalAfterGiftCard,
            'gift_card' => $appliedGiftCard ? [
                'code' => $appliedGiftCard['code'],
                'amount' => $giftCardDeduction,
                'remaining_balance' => $appliedGiftCard['remaining_balance'],
            ] : null,
            'gift_card_deduction' => $giftCardDeduction,
            'currency' => app(\App\Services\User\UserPreferenceService::class)->getPreferences()['currency'] ?? 'XOF',
            'shipping_method' => $selectedMethod,
            'stripeKey' => config('services.stripe.key'),
            'paystackKey' => config('services.paystack.public_key'),
            'user' => $customer ? [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ] : null,
            'defaultAddress' => $defaultAddress ? [
                'name' => $defaultAddress->name,
                'phone' => $defaultAddress->phone,
                'line1' => $defaultAddress->line1,
                'line2' => $defaultAddress->line2,
                'city' => $defaultAddress->city,
                'state' => $defaultAddress->state,
                'postal_code' => $defaultAddress->postal_code,
                'country' => $defaultAddress->country,
            ] : null,
            'addresses' => $addresses->map(fn($address) => [
                'id' => $address->id,
                'name' => $address->name,
                'phone' => $address->phone,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
                'is_default' => $address->is_default,
            ])->values()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $result = $this->getCartWithItems();

        if ($result instanceof RedirectResponse) {
            return $result;
        }
        $cart = $result['cart'];
        $cart_items = $result['cart_items'];

        $validatedData = $request->validate([
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:30'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['required', 'string', 'max:2'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'string', 'in:card,mobile_money,bank_transfer'],
            'mobile_money_provider' => ['nullable', 'string', 'max:50'],
            'accept_terms' => ['accepted'],
        ]);

        $customer = auth('customer')->user();
        $locale = app()->getLocale();
        $subtotal = $cart->subTotal();
        $shippingQuote = $cart->quoteShippingForItems($cart_items, true);
        if ((bool) ($shippingQuote['unavailable'] ?? false)) {
            return back()->withErrors([
                'cart' => $shippingQuote['reason'] ?? 'Shipping is unavailable for one or more items in your cart.',
            ]);
        }
        $shipping = (float) ($shippingQuote['total'] ?? 0);

        app(AffiliateReferralDiscountService::class)->autoApplyReferralCoupon();

        $coupon = session('cart_coupon');
        $discounts = $this->calculateDiscounts($cart, $cart_items, $coupon, $customer, $subtotal);
        $discount = @$discounts['amount'] ?? 0;
        $coupon = $discounts['coupon'] ?? null;
        $couponModel = $discounts['coupon_model'] ?? null;
        $promotionDiscounts = $discounts['promotion_discounts'] ?? [];
        $discountSource = $discounts['source'] ?? null;
        $settings = SiteSetting::query()->first();
        $shippingTotal = $this->applyShippingRules($shipping, $subtotal, $discount, $settings);
        $taxTotal = $this->calculateTax(max(0, $subtotal - $discount), $settings);
        $taxIncluded = (bool)($settings?->tax_included ?? false);
        $grandTotal = $subtotal + $shippingTotal - $discount + ($taxIncluded ? 0 : $taxTotal);

        $appliedGiftCard = $this->getAppliedGiftCard();
        $giftCardDeduction = $appliedGiftCard ? min($appliedGiftCard['amount'], max(0, $grandTotal)) : 0;
        $amountDue = max(0, $grandTotal - $giftCardDeduction);

        $cartContext = $this->buildCartContext($cart_items, $subtotal);
        $promotionEngine = app(PromotionEngine::class);
        $promotionModels = $promotionEngine->getApplicablePromotions($cartContext);
        $minimumRequirement = app(CartMinimumService::class)->evaluate($subtotal, $discount, $shippingTotal, $promotionModels, $coupon);
        if (!$minimumRequirement['passes']) {
            return back()
                ->withErrors(['cart' => $minimumRequirement['message']])
                ->with('minimum_cart_requirement', $minimumRequirement);
        }

        $discountSnapshot = $this->buildDiscountSnapshot(
            $discount,
            $discounts['label'] ?? null,
            $discountSource,
            $coupon ? $this->serializeCoupon($couponModel) : null,
            $promotionDiscounts,
            $cart[0]['currency'] ?? 'USD'
        );

        [$order, $payment] = DB::transaction(function () use ($validatedData, $cart, $cart_items, $discount, $coupon, $couponModel, $promotionDiscounts, $discountSource, $discountSnapshot, $subtotal, $shippingTotal, $taxTotal, $grandTotal, $locale) {
            $customer = Auth::guard('customer')->user();
            $isGuest = !$customer;
            if ($customer && $customer->locale !== $locale) {
                $customer->update(['locale' => $locale]);
            }

            // Create shipping address
            $shippingAddress = Address::create([
                // user_id references internal users; storefront customers should only set customer_id.
                'user_id' => null,
                'customer_id' => $customer?->id,
                'name' => trim($validatedData['first_name'] . ' ' . ($validatedData['last_name'] ?? '')),
                'phone' => $validatedData['phone'],
                'line1' => $validatedData['line1'],
                'line2' => $validatedData['line2'] ?? null,
                'city' => $validatedData['city'],
                'state' => $validatedData['state'] ?? null,
                'postal_code' => $validatedData['postal_code'] ?? null,
                'country' => strtoupper($validatedData['country']),
                'type' => 'shipping',
            ]);

            // Create order
            $order = Order::createWithGeneratedNumber([
                // user_id references internal users; storefront customers should only set customer_id.
                'user_id' => null,
                'customer_id' => $customer?->id,
                'guest_name' => $isGuest ? ($validatedData['first_name'] ?? null) : null,
                'guest_phone' => $isGuest ? ($validatedData['phone'] ?? null) : null,
                'is_guest' => $isGuest,
                'email' => $validatedData['email'],
                'locale' => $locale,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'currency' => app(\App\Services\User\UserPreferenceService::class)->getPreferences()['currency'] ?? 'XOF',
                'subtotal' => $subtotal,
                'shipping_total' => $shippingTotal,
                'shipping_total_estimated' => $shippingTotal,
                'tax_total' => $taxTotal,
                'discount_total' => $discount,
                'grand_total' => $grandTotal,
                'discount_snapshot' => $discountSnapshot,
                'discount_source' => $discountSource,
                'shipping_address_id' => $shippingAddress->id,
                'billing_address_id' => $shippingAddress->id,
                'shipping_method' => 'standard',
                'delivery_notes' => $validatedData['delivery_notes'] ?? null,
                'coupon_code' => $coupon['code'] ?? null,
                'placed_at' => now(),
            ]);

            // Create order items
            $fallbackProvider = SiteSetting::query()->value('default_fulfillment_provider_id');

            // Get currency conversion service
            $currencyConverter = app(\App\Services\Currency\CurrencyConversionService::class);
            $userCurrency = app(\App\Services\User\UserPreferenceService::class)->getPreferences()['currency'] ?? 'XOF';

            foreach ($cart_items as $line) {
                $providerId = $line['fulfillment_provider_id'] ?? $fallbackProvider;
                $supplierProduct = \App\Domain\Products\Models\SupplierProduct::query()
                    ->where('product_variant_id', $line['variant_id'])
                    ->when($providerId, fn ($query) => $query->where('fulfillment_provider_id', $providerId))
                    ->first();

                // Convert prices from USD to user's currency (XOF)
                $unitPriceInUsd = $line->getSinglePrice();
                try {
                    $unitPriceInUserCurrency = $currencyConverter->convertAmount($unitPriceInUsd, 'USD', $userCurrency);
                    // If conversion returns null, fall back to original price
                    if ($unitPriceInUserCurrency === null) {
                        \Log::warning('Currency conversion returned null during checkout', [
                            'usd_price' => $unitPriceInUsd,
                            'target_currency' => $userCurrency,
                            'order_id' => $order->id,
                        ]);
                        $unitPriceInUserCurrency = $unitPriceInUsd;
                    }
                } catch (\Throwable $e) {
                    \Log::error('Currency conversion failed during checkout', [
                        'usd_price' => $unitPriceInUsd,
                        'target_currency' => $userCurrency,
                        'error' => $e->getMessage(),
                        'order_id' => $order->id,
                    ]);
                    $unitPriceInUserCurrency = $unitPriceInUsd;
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
                        'name' => @$line?->product['name'],
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
                        'original_usd_price' => $unitPriceInUsd, // Store original USD for reference
                    ],
                ]);
            }

            foreach ($cart->shippings as $shipping) {
                $shipping = $shipping->toArray();
                $shipping['order_id'] = $order->id;
                $shipping['name'] = $shipping['logistic_name'];
                $shipping['price'] = $shipping['logistic_price'];
                OrderShipping::query()->create($shipping);
            }

            app(\App\Domain\Orders\Services\OrderCostBreakdownService::class)->recalculate($order);

            $this->recordPromotionUsage($order, $promotionDiscounts, $subtotal, $discountSource);
            $this->redeemCoupon($couponModel, $customer, $order, $discountSource, $discount);

            // Create payment
            $paymentProvider = in_array($validatedData['payment_method'], ['card', 'mobile_money'], true)
                ? 'paystack'
                : $validatedData['payment_method'];

            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => $paymentProvider,
                'status' => 'pending',
                'provider_reference' => null,
                'amount' => $amountDue,
                'currency' => $order->currency,
                'paid_at' => null,
                'meta' => [
                    'type' => 'checkout_pending',
                    'payment_method' => $validatedData['payment_method'],
                    'mobile_money_provider' => $validatedData['mobile_money_provider'] ?? null,
                    'coupon_code' => $coupon['code'] ?? null,
                    'gift_card_amount' => $giftCardDeduction,
                ],
            ]);

            if ($giftCardDeduction > 0) {
                $giftCard = GiftCard::find($appliedGiftCard['id']);
                if ($giftCard) {
                    $giftCard->decrement('balance', $giftCardDeduction);
                    if ((float) $giftCard->balance <= 0) {
                        $giftCard->update(['status' => 'redeemed']);
                    }
                    $order->giftCards()->attach($giftCard->id, ['amount_applied' => $giftCardDeduction]);
                    $order->update(['gift_card_amount' => $giftCardDeduction]);
                }
            }

            session()->forget('cart_gift_card');

            event(new OrderPlaced($order));

            return [$order, $payment];
        });

        // Handle payment based on method
        if (in_array($validatedData['payment_method'], ['card', 'mobile_money'], true)) {
            try {
                $paymentService = app(PaymentService::class);
                $init = $paymentService->initializePaystack(
                    $order,
                    $payment,
                    [
                        'email' => $validatedData['email'],
                        'name' => trim($validatedData['first_name'] . ' ' . ($validatedData['last_name'] ?? '')),
                        'phone' => $validatedData['phone'],
                        'mobile_provider' => $validatedData['mobile_money_provider'] ?? null,
                    ],
                    $validatedData['payment_method']
                );

                $authorizationUrl = $init['authorization_url'] ?? $init['checkout_url'] ?? null;

                if ($validatedData['payment_method'] === 'mobile_money' && ! $authorizationUrl) {
                    return redirect()
                        ->route('orders.confirmation', ['number' => $order->number])
                        ->with('status', $init['display_text'] ?? 'Authorize the mobile money charge on your phone to complete payment.');
                }

                if (!$authorizationUrl) {
                    return back()->withErrors([
                        'payment' => 'Payment provider did not return an authorization link.',
                    ]);
                }

                session()->forget(['cart', 'cart_coupon']);
                app(AbandonedCartService::class)->markRecovered();

                return redirect()->away($authorizationUrl);

            } catch (\Throwable $e) {
                Log::error('Payment initialization failed', ['error' => $e->getMessage()]);
                return back()->withErrors([
                    'payment' => 'Unable to start payment. Please try again.',
                ]);
            }
        }

        // For bank transfer or other offline methods
//        session()->forget(['cart', 'cart_coupon']);
        $cart->emptyCart();
        app(AbandonedCartService::class)->markRecovered();

        return redirect()->route('orders.confirmation', ['number' => $order->number]);
    }

    public function confirmation(string $number): Response
    {
        $order = Order::query()
            ->where('number', $number)
            ->with(['shippingAddress', 'billingAddress', 'orderItems'])
            ->firstOrFail();

        $upsellProducts = collect();
        if ($order->orderItems->isNotEmpty()) {
            $firstItemProductId = $order->orderItems->first()?->product_id;
            if ($firstItemProductId) {
                $product = Product::find($firstItemProductId);
                if ($product) {
                    $recommendationService = app(ProductRecommendationService::class);
                    $upsellProducts = $recommendationService
                        ->frequentlyBoughtTogether($product, 2)
                        ->map(fn (Product $p) => [
                            'id' => $p->id,
                            'name' => $p->name,
                            'slug' => $p->slug,
                            'price' => $p->selling_price,
                            'image' => $p->image?->url ?? $p->images?->first()?->url ?? null,
                            'currency' => $p->currency ?? 'USD',
                            'url' => $p->url ?? '/products/' . $p->slug,
                        ]);
                }
            }
        }

        return Inertia::render('Orders/Confirmation', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'email' => $order->email,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'currency' => $order->currency,
                'subtotal' => $order->subtotal,
                'shipping_total' => $order->shipping_total,
                'tax_total' => $order->tax_total,
                'discount_total' => $order->discount_total,
                'grand_total' => $order->grand_total,
                'items' => $order->orderItems->map(fn($item) => [
                    'id' => $item->id,
                    'name' => $item->snapshot['name'] ?? 'Item',
                    'variant' => $item->snapshot['variant'] ?? null,
                    'quantity' => $item->quantity,
                    'total' => $item->total,
                ]),
                'shippingAddress' => [
                    'name' => $order->shippingAddress?->name,
                    'line1' => $order->shippingAddress?->line1,
                    'city' => $order->shippingAddress?->city,
                    'country' => $order->shippingAddress?->country,
                    'phone' => $order->shippingAddress?->phone,
                ],
                'billingAddress' => [
                    'name' => $order->billingAddress?->name,
                    'line1' => $order->billingAddress?->line1,
                    'city' => $order->billingAddress?->city,
                    'country' => $order->billingAddress?->country,
                    'phone' => $order->billingAddress?->phone,
                ],
            ],
            'upsellProducts' => $upsellProducts,
        ]);
    }


    /**
     * Create quote payload from address
     */
    private function createQuotePayloadFromAddress(?Address $address): array
    {
        return [
            'country' => strtoupper($address?->country ?? 'CI'),
            'state' => $address?->state,
            'city' => $address?->city,
            'postal_code' => $address?->postal_code,
        ];
    }


    /**
     * Calculate best discount (coupon vs campaign)
     */
    private function calculateDiscounts($cart, $cart_items, ?array $coupon, ?Customer $customer, float $subtotal): array
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

    /**
     * Get current authenticated customer
     */


    /**
     * Apply shipping rules (free shipping threshold, handling fees)
     */
    private function applyShippingRules(float $shippingTotal, float $subtotal, float $discount, ?SiteSetting $settings): float
    {
        $eligibleTotal = max(0, $subtotal - $discount);
        $threshold = (float)($settings?->free_shipping_threshold ?? 0);
        $handlingFee = (float)($settings?->shipping_handling_fee ?? 0);

        if ($threshold > 0 && $eligibleTotal >= $threshold) {
            return 0.0;
        }

        if ($handlingFee > 0 && $shippingTotal > 0) {
            return round($shippingTotal + $handlingFee, 2);
        }

        return $shippingTotal;
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
     * Build a snapshot of applied discounts for order auditability.
     *
     * @param array<int, array<string, mixed>> $promotionDiscounts
     */
    private function buildDiscountSnapshot(
        float   $discountAmount,
        ?string $label,
        ?string $source,
        ?array  $coupon,
        array   $promotionDiscounts,
        string  $currency
    ): array
    {
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

    /**
     * @param array<int, array<string, mixed>> $promotionDiscounts
     */
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
        if (!$coupon || $discountSource !== 'coupon' || $discountAmount <= 0) {
            return;
        }

        $coupon->increment('uses');

        if (!$customer) {
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

    private function calculateTax(float $taxableAmount, ?SiteSetting $settings): float
    {
        if (!$settings || !$settings->tax_rate) {
            return 0.0;
        }

        return round($taxableAmount * ((float)$settings->tax_rate / 100), 2);
    }

    /**
     * Validate stock for all cart items
     */
    private function validateStock(array $cart): bool
    {
        foreach ($cart as $line) {
            // Check local stock first
            if (isset($line['stock_on_hand']) && is_numeric($line['stock_on_hand'])) {
                if ((int)$line['stock_on_hand'] < (int)$line['quantity']) {
                    return false;
                }
                continue; // local stock sufficient, skip CJ check
            }

            // Fallback to CJ API check
            if (!$this->validateCJStock([$line])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate stock using CJ Dropshipping API
     */
    private function validateCJStock(array $cart): bool
    {
        $client = app(CJDropshippingClient::class);

        foreach ($cart as $line) {
            // Prefer local stock snapshot if present
            if (isset($line['stock_on_hand']) && is_numeric($line['stock_on_hand'])) {
                if ((int)$line['stock_on_hand'] < (int)$line['quantity']) {
                    return false;
                }
                continue;
            }

            try {
                $response = null;

                if (isset($line['cj_vid'])) {
                    $response = $client->getStockByVid((string)$line['cj_vid']);
                } elseif (isset($line['sku'])) {
                    $response = $client->getStockBySku((string)$line['sku']);
                } elseif (isset($line['cj_pid'])) {
                    $response = $client->getStockByPid((string)$line['cj_pid']);
                } else {
                    continue;
                }

                $available = $this->sumStorage($response->data ?? null);

                if ($available < (int)$line['quantity']) {
                    return false;
                }
            } catch (ApiException $exception) {
                Log::warning('CJ stock check failed during checkout', [
                    'error' => $exception->getMessage(),
                    'line' => $line['id'] ?? null
                ]);

                if (!$this->fallbackStockCheck($line, (int)$line['quantity'])) {
                    return false;
                }
            } catch (\Throwable $exception) {
                Log::error('CJ stock check failed during checkout', [
                    'error' => $exception->getMessage(),
                    'line' => $line['id'] ?? null
                ]);

                if (!$this->fallbackStockCheck($line, (int)$line['quantity'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Sum storage numbers from CJ API response
     */
    private function sumStorage(mixed $payload): int
    {
        if (is_numeric($payload)) {
            return (int)$payload;
        }

        if (!is_array($payload)) {
            return 0;
        }

        $total = 0;
        $stockKeys = ['storageNum', 'totalInventoryNum', 'inventory', 'totalInventory'];

        $collect = function (mixed $node) use (&$collect, &$total, $stockKeys): void {
            if (!is_array($node)) {
                return;
            }

            foreach ($stockKeys as $key) {
                if (array_key_exists($key, $node) && is_numeric($node[$key])) {
                    $total += (int) $node[$key];
                }
            }

            foreach ($node as $value) {
                if (is_array($value)) {
                    $collect($value);
                }
            }
        };

        $collect($payload);

        return $total;
    }

    /**
     * Fallback stock check using local stock data
     */
    private function fallbackStockCheck(array $line, int $desiredQty): bool
    {
        if (isset($line['stock_on_hand']) && is_numeric($line['stock_on_hand'])) {
            return (int)$line['stock_on_hand'] >= $desiredQty;
        }

        // If no local stock data, assume stock is available
        return true;
    }

    private function getAppliedGiftCard(): ?array
    {
        return session('cart_gift_card');
    }

    public function applyGiftCard(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:255',
        ]);

        $customer = auth('customer')->user();
        if (!$customer) {
            return back()->withErrors(['gift_card' => 'Please sign in to use a gift card.']);
        }

        $giftCard = GiftCard::query()
            ->where('code', $data['code'])
            ->active()
            ->forCustomer($customer->id)
            ->first();

        if (!$giftCard) {
            session()->forget('cart_gift_card');
            return back()->withErrors(['gift_card' => 'Gift card not found, inactive, expired, or not assigned to you.']);
        }

        $subtotal = $this->getCartWithItems();
        if ($subtotal instanceof RedirectResponse) {
            return $subtotal;
        }
        $cart = $subtotal['cart'];
        $subtotalAmount = $cart->subTotal();

        $amountToApply = min((float) $giftCard->balance, $subtotalAmount);

        session(['cart_gift_card' => [
            'id' => $giftCard->id,
            'code' => $giftCard->code,
            'amount' => $amountToApply,
            'remaining_balance' => (float) $giftCard->balance - $amountToApply,
        ]]);

        return back()->with('status', __('Gift card :code applied (:amount).', [
            'code' => $giftCard->code,
            'amount' => number_format($amountToApply, 2),
        ]));
    }

    public function removeGiftCard(): RedirectResponse
    {
        session()->forget('cart_gift_card');
        return back()->with('status', __('Gift card removed.'));
    }

    private function processGiftCardDeduction(Order $order, float $grandTotal): float
    {
        $appliedGiftCard = $this->getAppliedGiftCard();
        if (!$appliedGiftCard) {
            return $grandTotal;
        }

        $giftCard = GiftCard::find($appliedGiftCard['id']);
        if (!$giftCard || $giftCard->status !== 'active' || (float) $giftCard->balance <= 0) {
            session()->forget('cart_gift_card');
            return $grandTotal;
        }

        $amountToDeduct = min($appliedGiftCard['amount'], (float) $giftCard->balance, $grandTotal);

        if ($amountToDeduct <= 0) {
            session()->forget('cart_gift_card');
            return $grandTotal;
        }

        $giftCard->decrement('balance', $amountToDeduct);
        if ((float) $giftCard->balance <= 0) {
            $giftCard->update(['status' => 'redeemed']);
        }

        $order->giftCards()->attach($giftCard->id, ['amount_applied' => $amountToDeduct]);
        $order->update(['gift_card_amount' => $amountToDeduct]);

        session()->forget('cart_gift_card');

        return max(0, $grandTotal - $amountToDeduct);
    }
}
