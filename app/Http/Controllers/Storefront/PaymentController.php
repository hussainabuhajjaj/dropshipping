<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Common\Models\Address;
use App\Domain\Orders\Services\OrderCostBreakdownService;
use App\Domain\Payments\PaymentService as DomainPaymentService;
use App\Domain\Products\Models\SupplierProduct;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\CartResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderShipping;
use App\Models\Payment;
use App\Models\GiftCard;
use App\Models\SiteSetting;
use App\Services\AbandonedCartService;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        protected DomainPaymentService $paymentService,
    ) {
    }

    public function index($type, $id = null)
    {
        $customer = auth('customer')->user();

        $item = $this->getItem($type, $id);
        if (! $item) {
            if ($type === 'cart') {
                return redirect()->route('products.index');
            }

            abort(404);
        }

        $summery = [];
        $items = [];
        if ($item instanceof Cart) {
            $summery = $item->getSummery();
            $items = (CartResource::collection($item->items))->jsonSerialize();

            $summery = $this->convertSummaryToXof($summery);
            $items = $this->convertItemsToXof($items);
        }

        $finalTotal = (float) ($summery['raw']['total'] ?? $summery['total'] ?? 0);

        $appliedGiftCard = session('cart_gift_card');
        $giftCardDeduction = 0;
        $giftCardData = null;
        if ($appliedGiftCard && $customer) {
            $giftCard = GiftCard::find($appliedGiftCard['id']);
            if ($giftCard && $giftCard->status === 'active' && (float) $giftCard->balance > 0) {
                // Convert gift card from USD to XOF (store uses XOF for checkout)
                $giftCardUsd = (float) $appliedGiftCard['amount'];
                $giftCardXof = app(CurrencyConversionService::class)->convertAmount($giftCardUsd, 'USD', 'XOF') ?? $giftCardUsd;
                $giftCardDeduction = min($giftCardXof, max(0, $finalTotal));
                $giftCardData = [
                    'code' => $giftCard->code,
                    'amount' => $giftCardUsd,
                    'amount_xof' => $giftCardDeduction,
                ];
                $finalTotal = max(0, $finalTotal - $giftCardDeduction);
                // Update summery raw total so PaymentSummary reflects the reduced total
                $summery['total'] = $finalTotal;
                $summery['raw']['total'] = $finalTotal;
                $summery['gift_card'] = [
                    'code' => $giftCard->code,
                    'amount' => $giftCardDeduction,
                    'label' => 'Carte cadeau (' . $giftCard->code . ')',
                ];
            } else {
                session()->forget('cart_gift_card');
            }
        }

        $defaultAddress = $customer?->addresses()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        $addresses = $customer?->addresses()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get() ?? collect();

        return Inertia::render('Payments/Index', [
            'customer' => $customer,
            'defaultAddress' => $defaultAddress,
            'addresses' => $addresses->map(fn ($address) => [
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
            'type' => $type,
            'id' => $id,
            'summery' => $summery,
            'final_total' => $finalTotal,
            'gift_card' => $giftCardData,
            'gift_card_deduction' => $giftCardDeduction,
            'items' => $items,
            'successMessage' => session('success'),
            'errorMessage' => session('error'),
            'errors' => session('errors') ? session('errors')->toArray() : (object) [],
        ]);
    }

    public function checkout(Request $request, $type, $id = null)
    {
        $customer = auth('customer')->user();

        $cart = $this->getItem($type, $id);
        if (! ($cart instanceof Cart)) {
            return response()->json([
                'status' => false,
                'message' => 'Unsupported payment type',
            ], 422);
        }

        if (! $cart->items || ! $cart->items->count()) {
            return redirect()->route('products.index');
        }

        $summery = $cart->getSummery();

        $extraValidationRules = $this->getItemValidationArray('cart');
        $request->validate(array_merge([
            'method' => 'required|in:card,mobile_money',
        ], $extraValidationRules));

        $method = (string) $request->input('method');

        session()->put('request_body', $request->all());

        try {
            $order = null;
            $payment = null;
            $init = null;

            DB::transaction(function () use ($customer, $cart, $summery, $request, $method, &$order, &$payment, &$init): void {
                $requestBody = (array) session()->get('request_body', []);

                $email = (string) ($requestBody['email'] ?? $customer->email ?? '');

                if ($email === '') {
                    throw new RuntimeException('Customer email missing for checkout.');
                }

                $isGuest = !$customer;
                $addressId = $request->integer('address_id');
                $shippingAddress = $addressId && $customer
                    ? Address::query()
                        ->where('customer_id', $customer->id)
                        ->findOrFail($addressId)
                    : Address::query()->create([
                        'user_id' => null,
                        'customer_id' => $customer?->id,
                        'name' => trim(implode(' ', array_filter([
                            (string) ($requestBody['first_name'] ?? ''),
                            (string) ($requestBody['last_name'] ?? ''),
                        ]))),
                        'phone' => (string) ($requestBody['phone'] ?? ''),
                        'line1' => (string) ($requestBody['line1'] ?? ''),
                        'line2' => filled($requestBody['line2'] ?? null) ? (string) $requestBody['line2'] : null,
                        'city' => (string) ($requestBody['city'] ?? ''),
                        'state' => filled($requestBody['state'] ?? null) ? (string) $requestBody['state'] : null,
                        'postal_code' => filled($requestBody['postal_code'] ?? null) ? (string) $requestBody['postal_code'] : null,
                        'country' => strtoupper((string) ($requestBody['country'] ?? '')),
                        'type' => 'shipping',
                    ]);

                $coupon = $summery['coupon'] ?? null;
                $couponModel = $summery['coupon_model'] ?? null;
                $discountSnapshot = buildDiscountSnapshot(
                    $summery['discount'] ?? null,
                    $summery['discount_label'] ?? null,
                    $summery['discount_source'] ?? null,
                    is_array($coupon) ? $coupon : ($couponModel ? $couponModel->serializeCoupon() : null),
                    $summery['promotionDiscounts'] ?? null,
                    $cart[0]['currency'] ?? 'USD'
                );

                $order = Order::createWithGeneratedNumber([
                    'user_id' => null,
                    'customer_id' => $customer?->id,
                    'guest_name' => $isGuest ? trim(implode(' ', array_filter([$requestBody['first_name'] ?? '', $requestBody['last_name'] ?? '']))) : null,
                    'guest_phone' => $isGuest ? ($requestBody['phone'] ?? null) : null,
                    'is_guest' => $isGuest,
                    'email' => $email,
                    'locale' => app()->getLocale(),
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'currency' => $cart->items->first()?->variant['currency'] ?? 'USD',
                    'subtotal' => $summery['subtotal'] ?? null,
                    'shipping_total' => $summery['shippingTotal'] ?? null,
                    'shipping_total_estimated' => $summery['shippingTotal'] ?? null,
                    'tax_total' => $summery['tax_total'] ?? null,
                    'discount_total' => $summery['discount'] ?? null,
                    'grand_total' => $summery['total'] ?? null,
                    'discount_snapshot' => $discountSnapshot,
                    'discount_source' => $summery['discount_source'] ?? null,
                    'shipping_address_id' => $shippingAddress->id,
                    'billing_address_id' => $shippingAddress->id,
                    'shipping_method' => 'standard',
                    'delivery_notes' => $requestBody['delivery_notes'] ?? null,
                    'coupon_code' => is_array($coupon) ? ($coupon['code'] ?? null) : ($coupon?->code ?? null),
                    'placed_at' => now(),
                ]);

                $fallbackProvider = SiteSetting::query()->value('default_fulfillment_provider_id');
                $currencyConverter = app(\App\Services\Currency\CurrencyConversionService::class);
                $userCurrency = app(\App\Services\User\UserPreferenceService::class)->getPreferences()['currency'] ?? 'XOF';

                foreach ($cart->items as $line) {
                    $providerId = $line['fulfillment_provider_id'] ?? $fallbackProvider;

                    $supplierProduct = SupplierProduct::query()
                        ->where('product_variant_id', $line['variant_id'])
                        ->when($providerId, fn ($query) => $query->where('fulfillment_provider_id', $providerId))
                        ->first();

                    // Convert prices from USD to user's currency (XOF)
                    $unitPriceInUsd = $line->getSinglePrice();
                    try {
                        $unitPriceInUserCurrency = $currencyConverter->convertAmount($unitPriceInUsd, 'USD', $userCurrency);
                        if ($unitPriceInUserCurrency === null) {
                            \Log::warning('Currency conversion returned null in payment controller', [
                                'usd_price' => $unitPriceInUsd,
                                'target_currency' => $userCurrency,
                                'order_id' => $order->id,
                            ]);
                            $unitPriceInUserCurrency = $unitPriceInUsd;
                        }
                    } catch (\Throwable $e) {
                        \Log::error('Currency conversion failed in payment controller', [
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
                            'name' => $line?->product['name'] ?? null,
                            'variant' => $line?->variant['title'] ?? null,
                            'supplier_type' => $line->product?->supplier_type,
                        ],
                        'meta' => [
                            'media' => $line['media'] ?? null,
                            'coupon_code' => is_array($coupon) ? ($coupon['code'] ?? null) : ($coupon?->code ?? null),
                            'supplier_type' => $line->product?->supplier_type,
                            'supplier_product_id' => $supplierProduct?->id,
                            'external_product_id' => $supplierProduct?->external_product_id,
                            'external_sku' => $supplierProduct?->external_sku,
                        ],
                    ]);
                }

                app(OrderCostBreakdownService::class)->recalculate($order);

                foreach ($cart->shippings as $shipping) {
                    $shippingArr = $shipping->toArray();
                    $shippingArr['order_id'] = $order->id;
                    $shippingArr['name'] = $shippingArr['logistic_name'] ?? null;
                    $shippingArr['price'] = $shippingArr['logistic_price'] ?? null;
                    OrderShipping::query()->create($shippingArr);
                }

                // KoraPay payment provider (commented out - using Paystack instead)
                /*
                $payment = Payment::query()->create([
                    'order_id' => $order->id,
                    'provider' => 'korapay',
                    'status' => 'pending',
                    'provider_reference' => null,
                    'amount' => (float) ($order->grand_total ?? 0),
                    'currency' => (string) ($order->currency ?? 'USD'),
                    'meta' => [
                        'storefront' => true,
                        'request' => $requestBody,
                    ],
                ]);

                // Use a stable return endpoint. The redirect handler resolves the order by Korapay reference anyway.
                // This avoids provider-side issues with dynamic redirect URLs.
                $returnUrl = route('pay.redirect', ['type' => 'cart']);

                $init = $this->paymentService->initializeKorapay(
                    order: $order,
                    payment: $payment,
                    customer: [
                        'email' => $customer->email,
                        'name' => $customer->name,
                    ],
                    method: $method,
                    returnUrl: $returnUrl,
                );
                */

                // Paystack payment provider
                $payment = Payment::query()->create([
                    'order_id' => $order->id,
                    'provider' => 'paystack',
                    'status' => 'pending',
                    'provider_reference' => null,
                    'amount' => (float) ($order->grand_total ?? 0),
                    'currency' => (string) ($order->currency ?? 'USD'),
                    'meta' => [
                        'storefront' => true,
                        'request' => $requestBody,
                    ],
                ]);

                // Use a stable return endpoint. The redirect handler resolves the order by Paystack reference anyway.
                // This avoids provider-side issues with dynamic redirect URLs.
                $returnUrl = route('pay.redirect', ['type' => 'cart']);

                Log::info('Paystack initialization attempt', [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                    'amount' => $order->grand_total,
                    'currency' => $order->currency,
                    'payment_method' => $method,
                ]);
                
                $init = $this->paymentService->initializePaystack(
                    order: $order,
                    payment: $payment,
                    customer: [
                        'email' => $customer->email,
                        'name' => $customer->name,
                    ],
                    method: $method,
                    returnUrl: $returnUrl,
                );
                
                Log::info('Paystack initialization successful', [
                    'reference' => $init['reference'] ?? null,
                    'authorization_url' => $init['authorization_url'] ?? null,
                ]);
            });

            return response()->json([
                'status' => true,
                'data' => [
                    'redirect' => $init['authorization_url'] ?? $init['checkout_url'] ?? null,
                    'reference' => $init['reference'] ?? null,
                    'order_id' => $order?->id,
                    'order_number' => $order?->number,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Storefront checkout failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Checkout failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function redirect(Request $request, $type, $id = null)
    {
        $reference = (string) ($request->input('reference')
            ?? $request->input('payment_reference')
            ?? $request->input('transaction_reference')
            ?? $request->query('trxref')
            ?? '');

        if (! $reference) {
            abort(404);
        }

        // KoraPay provider check (commented out - using Paystack instead)
        /*
        $existing = Payment::query()
            ->where('provider', 'korapay')
            ->where('provider_reference', $reference)
            ->with('order')
            ->latest('id')
            ->first();
        */

        // Paystack provider check
        $existing = Payment::query()
            ->where('provider', 'paystack')
            ->where('provider_reference', $reference)
            ->with('order')
            ->latest('id')
            ->first();

        // Track redirects so we can distinguish "paid via webhook/verify but never redirected back".
        if ($existing) {
            $meta = is_array($existing->meta) ? $existing->meta : [];
            $meta['redirect_hit_at'] = now()->toISOString();
            $meta['redirect_payload'] = [
                'query' => $request->query(),
                'input' => $request->all(),
                'path' => $request->path(),
            ];
            $existing->forceFill(['meta' => $meta])->save();
        }

        if ($existing?->status === 'paid' && $existing?->order) {
            return redirect()->route('orders.confirmation', ['number' => $existing->order->number]);
        }

        // KoraPay verification (commented out - using Paystack instead)
        /*
        $payment = $this->paymentService->verifyKorapay($reference);
        */

        // Paystack verification
        $payment = $this->paymentService->verifyPaystack($reference);
        $order = $payment->order()->first();

        if ($payment->status === 'paid' && $order) {
            $customer = auth('customer')->user();
            if ($customer && (int) $order->customer_id === (int) $customer->id) {
                $cart = Cart::query()->where('user_id', $customer->id)->first();
                $cart?->emptyCart();
                app(AbandonedCartService::class)->markRecovered();
            }

            return redirect()->route('orders.confirmation', ['number' => $order->number]);
        }

        return redirect()->route('pay.index', ['type' => 'cart'])->with('error', 'Payment could not be confirmed.');
    }

    private function getItem($type, $id)
    {
        if ($type === 'cart') {
            $customer = auth('customer')->user();
            if (! $customer) {
                return null;
            }

            return Cart::query()
                ->where('user_id', $customer->id)
                ->with('items')
                ->first();
        }

        if ($type === 'order') {
            return Order::query()->findOrFail($id);
        }

        return null;
    }

    private function getItemValidationArray(string $type): array
    {
        if ($type !== 'cart') {
            return [];
        }

        $customerId = auth('customer')->id();
        $addressId = request()->get('address_id');
        if (filled($addressId)) {
            return [
                'address_id' => [
                    'required',
                    'integer',
                    Rule::exists('addresses', 'id')->where(fn ($query) => $query->where('customer_id', $customerId)),
                ],
                'delivery_notes' => ['nullable', 'string', 'max:500'],
            ];
        }

        return [
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
        ];
    }

    private function convertSummaryToXof(array $summary): array
    {
        $sourceCurrency = strtoupper((string) ($summary['currency'] ?? config('currency.base', 'USD')));

        $summary['subtotal'] = $this->convertMoneyToXof($summary['subtotal'] ?? 0, $sourceCurrency);
        $summary['shipping'] = $this->convertMoneyToXof($summary['shipping'] ?? 0, $sourceCurrency);
        $summary['shippingTotal'] = $this->convertMoneyToXof($summary['shippingTotal'] ?? ($summary['shipping'] ?? 0), $sourceCurrency);
        $summary['discount'] = $this->convertMoneyToXof($summary['discount'] ?? 0, $sourceCurrency);
        $summary['tax_total'] = $this->convertMoneyToXof($summary['tax_total'] ?? 0, $sourceCurrency);
        $summary['total'] = $this->convertMoneyToXof($summary['total'] ?? 0, $sourceCurrency);
        $summary['currency'] = 'XOF';

        if (isset($summary['raw']) && is_array($summary['raw'])) {
            $summary['raw']['subtotal'] = $summary['subtotal'];
            $summary['raw']['shipping'] = $summary['shipping'];
            $summary['raw']['discount'] = $summary['discount'];
            $summary['raw']['tax_total'] = $summary['tax_total'];
            $summary['raw']['total'] = $summary['total'];
            $summary['raw']['currency'] = 'XOF';
        }

        if (isset($summary['minimum_cart_requirement']) && is_array($summary['minimum_cart_requirement'])) {
            if (isset($summary['minimum_cart_requirement']['threshold'])) {
                $summary['minimum_cart_requirement']['threshold'] = $this->convertMoneyToXof($summary['minimum_cart_requirement']['threshold'], $sourceCurrency);
            }

            if (isset($summary['minimum_cart_requirement']['effective_total'])) {
                $summary['minimum_cart_requirement']['effective_total'] = $this->convertMoneyToXof($summary['minimum_cart_requirement']['effective_total'], $sourceCurrency);
            }

            $summary['minimum_cart_requirement']['message'] = null;
        }

        if (isset($summary['promotionDiscounts']) && is_array($summary['promotionDiscounts'])) {
            $summary['promotionDiscounts'] = array_map(function ($promotion) use ($sourceCurrency) {
                if (! is_array($promotion)) {
                    return $promotion;
                }

                foreach (['amount', 'discount', 'value'] as $key) {
                    if (isset($promotion[$key]) && is_numeric($promotion[$key])) {
                        $promotion[$key] = $this->convertMoneyToXof($promotion[$key], $sourceCurrency);
                    }
                }

                return $promotion;
            }, $summary['promotionDiscounts']);
        }

        if (isset($summary['appliedPromotions']) && is_array($summary['appliedPromotions'])) {
            $summary['appliedPromotions'] = array_map(function ($promotion) use ($sourceCurrency) {
                if (! is_array($promotion)) {
                    return $promotion;
                }

                if (($promotion['value_type'] ?? null) === 'fixed' && isset($promotion['value']) && is_numeric($promotion['value'])) {
                    $promotion['value'] = $this->convertMoneyToXof($promotion['value'], $sourceCurrency);
                }

                return $promotion;
            }, $summary['appliedPromotions']);
        }

        return $summary;
    }

    private function convertItemsToXof(array $items): array
    {
        return array_map(function ($item) {
            if (! is_array($item)) {
                return $item;
            }

            $sourceCurrency = strtoupper((string) ($item['currency'] ?? config('currency.base', 'USD')));
            $item['price'] = $this->convertMoneyToXof($item['price'] ?? 0, $sourceCurrency);

            if (isset($item['compare_at_price']) && $item['compare_at_price'] !== null) {
                $item['compare_at_price'] = $this->convertMoneyToXof($item['compare_at_price'], $sourceCurrency);
            }

            $item['currency'] = 'XOF';

            return $item;
        }, $items);
    }

    private function convertMoneyToXof(float|int|string|null $amount, string $sourceCurrency): float
    {
        if (! is_numeric($amount)) {
            return 0.0;
        }

        $amount = (float) $amount;
        $sourceCurrency = strtoupper(trim($sourceCurrency));

        if ($sourceCurrency === 'XOF') {
            return round($amount, 0);
        }

        $converted = app(CurrencyConversionService::class)->convertAmount($amount, $sourceCurrency, 'XOF');

        return round((float) ($converted ?? $amount), 0);
    }
}
