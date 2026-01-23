<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\CartResource;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PromotionUsage;
use App\Models\SiteSetting;
use App\Domain\Common\Models\Address;
use App\Domain\Orders\Models\OrderAuditLog;
use App\Events\Orders\OrderPlaced;
use App\Services\CampaignManager;
use App\Services\Coupons\CouponValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExpressCheckoutController extends Controller
{
    /**
     * Create express payment intent for Apple Pay/Google Pay via Stripe/Paystack.
     */
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $cart = $this->resolveCartLines();
        if (empty($cart)) {
            return response()->json(['error' => 'Cart is empty'], 400);
        }

        $data = $request->validate([
            'provider' => ['required', 'string', 'in:stripe,paystack'],
            'shipping_address' => ['sometimes', 'array'],
            'shipping_address.name' => ['required_with:shipping_address', 'string', 'max:120'],
            'shipping_address.line1' => ['required_with:shipping_address', 'string', 'max:255'],
            'shipping_address.city' => ['required_with:shipping_address', 'string', 'max:120'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:30'],
            'shipping_address.country' => ['required_with:shipping_address', 'string', 'max:2'],
        ]);

        $subtotal = $this->subtotal($cart);
        $customer = $request->user('customer');
        
        // Calculate shipping if address provided
        $shipping = 0;
        if (isset($data['shipping_address'])) {
            $shippingQuote = $this->quoteShipping($cart, $data['shipping_address']);
            $shipping = $shippingQuote['shipping_total'] ?? 0;
        }

        $coupon = session('cart_coupon');
        $discounts = $this->calculateDiscounts($cart, $coupon, $customer, $subtotal);
        $discount = $discounts['amount'];
        $coupon = $discounts['coupon'] ?? null;
        
        $settings = SiteSetting::query()->first();
        $taxTotal = $this->calculateTax(max(0, $subtotal - $discount), $settings);
        $taxIncluded = (bool) ($settings?->tax_included ?? false);
        
        $grandTotal = $subtotal + $shipping - $discount + ($taxIncluded ? 0 : $taxTotal);

        try {
            if ($data['provider'] === 'stripe') {
                $intent = $this->createStripePaymentIntent($grandTotal, $cart[0]['currency'] ?? 'USD');
                
                return response()->json([
                    'clientSecret' => $intent->client_secret,
                    'amount' => $grandTotal,
                    'currency' => strtoupper($cart[0]['currency'] ?? 'USD'),
                ]);
            }

            if ($data['provider'] === 'paystack') {
                // Paystack express checkout via their payment request button
                return response()->json([
                    'public_key' => config('services.paystack.public_key'),
                    'amount' => (int) ($grandTotal * 100), // Convert to kobo/cents
                    'currency' => strtoupper($cart[0]['currency'] ?? 'USD'),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Express checkout payment intent failed', [
                'provider' => $data['provider'],
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to initialize payment'], 500);
        }

        return response()->json(['error' => 'Invalid provider'], 400);
    }

    /**
     * Complete express checkout after payment confirmation.
     */
    public function complete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'in:stripe,paystack'],
            'payment_intent_id' => ['required_if:provider,stripe', 'string'],
            'reference' => ['required_if:provider,paystack', 'string'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:30'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.name' => ['required', 'string', 'max:120'],
            'shipping_address.line1' => ['required', 'string', 'max:255'],
            'shipping_address.line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.state' => ['nullable', 'string', 'max:120'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:30'],
            'shipping_address.country' => ['required', 'string', 'max:2'],
        ]);

        $cart = $this->resolveCartLines();
        if (empty($cart)) {
            return response()->json(['error' => 'Cart is empty'], 400);
        }

        try {
            [$order, $payment] = DB::transaction(function () use ($data, $cart, $request) {
                $customer = $request->user('customer');
                $subtotal = $this->subtotal($cart);
                
                $shippingQuote = $this->quoteShipping($cart, $data['shipping_address']);
                $coupon = session('cart_coupon');
                $discounts = $this->calculateDiscounts($cart, $coupon, $customer, $subtotal);
                $discount = $discounts['amount'];
                $coupon = $discounts['coupon'] ?? null;
                $couponModel = $discounts['coupon_model'] ?? null;
                $promotionDiscounts = $discounts['promotion_discounts'] ?? [];
                $discountSource = $discounts['source'] ?? null;
                $locale = app()->getLocale();
                
                $settings = SiteSetting::query()->first();
                $shippingTotal = (float) ($shippingQuote['shipping_total'] ?? 0);
                $taxTotal = $this->calculateTax(max(0, $subtotal - $discount), $settings);
                $taxIncluded = (bool) ($settings?->tax_included ?? false);
                $grandTotal = $subtotal + $shippingTotal - $discount + ($taxIncluded ? 0 : $taxTotal);

                // Create shipping address
                $shippingAddress = Address::create([
                    'user_id' => null,
                    'customer_id' => $customer?->id,
                    'name' => $data['shipping_address']['name'],
                    'phone' => $data['phone'],
                    'line1' => $data['shipping_address']['line1'],
                    'line2' => $data['shipping_address']['line2'] ?? null,
                    'city' => $data['shipping_address']['city'],
                    'state' => $data['shipping_address']['state'] ?? null,
                    'postal_code' => $data['shipping_address']['postal_code'] ?? null,
                    'country' => strtoupper($data['shipping_address']['country']),
                    'type' => 'shipping',
                ]);

                // Create order
                if ($customer && $customer->locale !== $locale) {
                    $customer->update(['locale' => $locale]);
                }

                $order = Order::create([
                    'number' => $this->generateNumber(),
                    'user_id' => null,
                    'customer_id' => $customer?->id,
                    'guest_name' => $customer ? null : $data['shipping_address']['name'],
                    'guest_phone' => $customer ? null : $data['phone'],
                    'is_guest' => ! $customer,
                    'email' => $data['email'],
                    'locale' => $locale,
                    'status' => 'pending',
                    'payment_status' => 'unpaid',
                    'currency' => $cart[0]['currency'] ?? 'USD',
                    'subtotal' => $subtotal,
                    'shipping_total' => $shippingTotal,
                    'tax_total' => $taxTotal,
                    'discount_total' => $discount,
                    'grand_total' => $grandTotal,
                    'shipping_address_id' => $shippingAddress->id,
                    'billing_address_id' => $shippingAddress->id,
                    'shipping_method' => $shippingQuote['shipping_method'] ?? 'standard',
                    'coupon_code' => $coupon['code'] ?? null,
                    'placed_at' => now(),
                ]);

                // Create order items
                $fallbackProvider = SiteSetting::query()->value('default_fulfillment_provider_id');
                foreach ($cart as $line) {
                    $order->orderItems()->create([
                        'product_variant_id' => $line['variant_id'],
                        'fulfillment_provider_id' => $line['fulfillment_provider_id'] ?? $fallbackProvider,
                        'fulfillment_status' => 'pending',
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['price'],
                        'total' => $line['price'] * $line['quantity'],
                        'snapshot' => [
                            'name' => $line['name'],
                            'variant' => $line['variant'],
                        ],
                        'meta' => [
                            'media' => $line['media'],
                            'coupon_code' => $coupon['code'] ?? null,
                        ],
                    ]);
                }

                // Create payment record
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'provider' => $data['provider'],
                    'status' => 'pending',
                    'provider_reference' => $data['payment_intent_id'] ?? $data['reference'],
                    'amount' => $order->grand_total,
                    'currency' => $order->currency,
                    'paid_at' => null,
                    'meta' => [
                        'type' => 'express_checkout',
                        'payment_method' => $data['provider'] === 'stripe' ? 'apple_pay_google_pay' : 'mobile_money',
                    ],
                ]);

                event(new OrderPlaced($order));

                $this->recordPromotionUsage($order, $promotionDiscounts);
                $this->redeemCoupon($couponModel, $customer, $order, $discountSource, $discount);

                return [$order, $payment];
            });

            // Clear cart
            session()->forget(['cart', 'cart_coupon']);

            return response()->json([
                'success' => true,
                'order_number' => $order->number,
                'redirect_url' => route('orders.confirmation', ['number' => $order->number]),
            ]);
        } catch (\Throwable $e) {
            Log::error('Express checkout completion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to complete order'], 500);
        }
    }

    private function createStripePaymentIntent(float $amount, string $currency): object
    {
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        return $stripe->paymentIntents->create([
            'amount' => (int) ($amount * 100), // Convert to cents
            'currency' => strtolower($currency),
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'metadata' => [
                'source' => 'express_checkout',
            ],
        ]);
    }

    private function subtotal(array $cart): float
    {
        return collect($cart)->sum(fn ($line) => $line['price'] * $line['quantity']);
    }

    private function quoteShipping(array $cart, array $addressData): array
    {
        // Integrate with CJ Dropshipping API for real shipping quote
        try {
            // Find first line with CJ product info
            $line = collect($cart)->first(fn($l) => !empty($l['cj_pid']) && !empty($l['cj_vid']));
            if (!$line) {
                return [
                    'shipping_total' => 0,
                    'shipping_method' => 'unavailable',
                    'error' => 'No CJ product in cart',
                ];
            }

            $payload = [
                'productList' => [[
                    'pid' => $line['cj_pid'],
                    'vid' => $line['cj_vid'],
                    'num' => $line['quantity'],
                ]],
                'country' => $addressData['country'] ?? '',
                'province' => $addressData['state'] ?? '',
                'city' => $addressData['city'] ?? '',
                'address' => $addressData['line1'] ?? '',
                'zip' => $addressData['postal_code'] ?? '',
            ];

            // Use domain client for freightCalculate
            $cj = \App\Domain\Fulfillment\Clients\CJDropshippingClient::fromConfig();
            $resp = $cj->freightCalculate($payload);
            $data = $resp->data ?? [];
            if (!empty($data['freightList']) && is_array($data['freightList'])) {
                $best = collect($data['freightList'])->sortBy('freight')->first();
                return [
                    'shipping_total' => (float) ($best['freight'] ?? 0),
                    'shipping_method' => $best['logisticName'] ?? 'CJ',
                    'freightList' => $data['freightList'],
                ];
            }
            return [
                'shipping_total' => 0,
                'shipping_method' => 'unavailable',
                'error' => $data['msg'] ?? 'No shipping options',
            ];
        } catch (\Throwable $e) {
            \Log::error('CJ shipping quote failed', ['error' => $e->getMessage()]);
            return [
                'shipping_total' => 0,
                'shipping_method' => 'unavailable',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function calculateDiscounts(array $cart, ?array $coupon, $customer, float $subtotal): array
    {
        $couponValidator = app(CouponValidator::class);
        $couponModel = $couponValidator->resolveFromSession($coupon);
        if ($couponModel) {
            $error = $couponValidator->validateForCart($couponModel, $cart, $subtotal, $customer);
            if ($error) {
                session()->forget('cart_coupon');
                $couponModel = null;
                $coupon = null;
            }
        }
        $couponDiscount = $couponModel ? $couponValidator->calculateDiscount($couponModel, $subtotal) : 0.0;
        $campaign = app(CampaignManager::class)->bestForCart($this->normalizeCartLines($cart), $subtotal, $customer);

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

    private function normalizeCartLines(array $cart): array
    {
        $lines = $cart;
        $productIds = collect($lines)->pluck('product_id')->filter()->unique()->values();
        if ($productIds->isEmpty()) {
            return $lines;
        }

        $categoryMap = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('category_id', 'id');

        foreach ($lines as $index => $line) {
            if (! isset($line['category_id']) && isset($line['product_id'])) {
                $lines[$index]['category_id'] = $categoryMap[$line['product_id']] ?? null;
            }
        }

        return $lines;
    }

    private function resolveCartLines(): array
    {
        $cartModel = Cart::query()
            ->where('user_id', auth('customer')->id())
            ->orWhere('session_id', session()->id())
            ->with(['items.product.images', 'items.variant'])
            ->first();

        if ($cartModel && $cartModel->items->isNotEmpty()) {
            $lines = CartResource::collection($cartModel->items)->jsonSerialize();
            session(['cart' => $lines]);
            return $this->normalizeCartLines($lines);
        }

        $cart = session('cart', []);
        if (! empty($cart)) {
            return $this->normalizeCartLines($cart);
        }

        return [];
    }

    /**
     * @param array<int, array<string, mixed>> $promotionDiscounts
     */
    private function recordPromotionUsage(Order $order, array $promotionDiscounts): void
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
                'used_at' => $now,
            ]);
        }

        OrderAuditLog::create([
            'order_id' => $order->id,
            'user_id' => null,
            'action' => 'promotion_applied',
            'note' => 'Promotions applied during express checkout',
            'payload' => [
                'discounts' => $promotionDiscounts,
            ],
        ]);
    }

    private function redeemCoupon(?Coupon $coupon, $customer, Order $order, ?string $discountSource, float $discountAmount): void
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

    private function calculateTax(float $taxableAmount, $settings): float
    {
        if (! $settings || ! $settings->tax_rate) {
            return 0;
        }

        return $taxableAmount * ($settings->tax_rate / 100);
    }

    private function generateNumber(): string
    {
        return 'AZR' . strtoupper(Str::random(10));
    }
}
