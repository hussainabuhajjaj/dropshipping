<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Common\Models\Address;
use App\Events\Orders\OrderPaid;
use App\Events\Orders\OrderPlaced;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Domain\Products\Models\ProductVariant;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Fulfillment\Services\CJFreightService;
use Illuminate\Support\Facades\Log;
use App\Models\Coupon;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Infrastructure\Payments\Paystack\PaystackService;
use App\Services\Api\ApiException;
use App\Services\AbandonedCartService;
use App\Services\CampaignManager;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $cart = session('cart', []);
        if (empty($cart)) {
            return redirect()->route('products.index');
        }

        if (! $this->validateStock($cart)) {
            return back()->withErrors(['cart' => 'One or more items are out of stock. Please adjust your cart.']);
        }

        $subtotal = $this->subtotal($cart);
        $customer = $this->currentCustomer();
        app(AbandonedCartService::class)->capture($cart, $customer?->email, $customer?->id);
        $defaultAddress = $customer?->addresses()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
        $quotePayload = $this->quotePayloadFromAddress($defaultAddress);
        $shippingQuote = $this->quoteShipping($cart, $quotePayload);
        $shipping = $shippingQuote['shipping_total'] ?? 0;
        $selectedMethod = $shippingQuote['shipping_method'] ?? 'standard';
        $coupon = session('cart_coupon');
        $discounts = $this->calculateDiscounts($cart, $coupon, $customer, $subtotal);
        $discount = $discounts['amount'];
        $settings = SiteSetting::query()->first();
        $taxTotal = $this->calculateTax(max(0, $subtotal - $discount), $settings);
        $taxIncluded = (bool) ($settings?->tax_included ?? false);
        $total = $subtotal + $shipping - $discount + ($taxIncluded ? 0 : $taxTotal);

        return Inertia::render('Checkout/Index', [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'discount' => $discount,
            'coupon' => $coupon,
            'discount_label' => $discounts['label'],
            'tax_total' => $taxTotal,
            'tax_label' => $settings?->tax_label ?? 'Tax',
            'tax_included' => $taxIncluded,
            'total' => $total,
            'currency' => $cart[0]['currency'] ?? 'USD',
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cart = session('cart', []);
        if (empty($cart)) {
            return redirect()->route('products.index');
        }

        $data = $request->validate([
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
            'accept_terms' => ['accepted'],
        ]);

        $customer = $this->currentCustomer();
        $subtotal = $this->subtotal($cart);
        $shippingQuote = $this->quoteShipping($cart, $data);
        $coupon = session('cart_coupon');
        $discounts = $this->calculateDiscounts($cart, $coupon, $customer, $subtotal);
        $discount = $discounts['amount'];
        $settings = SiteSetting::query()->first();
        $shippingTotal = $this->applyShippingRules(
            (float) ($shippingQuote['shipping_total'] ?? 0),
            $subtotal,
            $discount,
            $settings
        );
        $taxTotal = $this->calculateTax(max(0, $subtotal - $discount), $settings);
        $taxIncluded = (bool) ($settings?->tax_included ?? false);
        $grandTotal = $subtotal + $shippingTotal - $discount + ($taxIncluded ? 0 : $taxTotal);

        [$order, $customer, $payment] = DB::transaction(function () use ($data, $cart, $shippingQuote, $discount, $coupon, $subtotal, $shippingTotal, $taxTotal, $grandTotal) {
            $customer = Auth::guard('customer')->user();
            $isGuest = false;

            // Only auto-create customer if they explicitly register, not for guest checkout
            if (! $customer) {
                // Guest checkout - no customer account creation
                $isGuest = true;
                $customer = null;
            }

            $shippingAddress = Address::create([
                'user_id' => null,
                'customer_id' => $customer?->id,
                'name' => trim($data['first_name'] . ' ' . ($data['last_name'] ?? '')),
                'phone' => $data['phone'],
                'line1' => $data['line1'],
                'line2' => $data['line2'] ?? null,
                'city' => $data['city'],
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => strtoupper($data['country']),
                'type' => 'shipping',
            ]);

            $order = Order::create([
                'number' => $this->generateNumber(),
                'user_id' => null,
                'customer_id' => $customer?->id,
                'guest_name' => $isGuest ? trim($data['first_name'] . ' ' . ($data['last_name'] ?? '')) : null,
                'guest_phone' => $isGuest ? $data['phone'] : null,
                'is_guest' => $isGuest,
                'email' => $data['email'],
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'currency' => $cart[0]['currency'] ?? 'USD',
                'subtotal' => $subtotal,
                'shipping_total' => $shippingTotal,
                'shipping_total_estimated' => $shippingTotal,
                'tax_total' => $taxTotal,
                'discount_total' => $discount,
                'grand_total' => $grandTotal,
                'shipping_address_id' => $shippingAddress->id,
                'billing_address_id' => $shippingAddress->id,
                'shipping_method' => $shippingQuote['shipping_method'] ?? 'standard',
                'delivery_notes' => $data['delivery_notes'] ?? null,
                'coupon_code' => $coupon['code'] ?? null,
                'placed_at' => now(),
            ]);

            $fallbackProvider = SiteSetting::query()->value('default_fulfillment_provider_id');

            collect($cart)->each(function (array $line) use ($order, $fallbackProvider) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $line['variant_id'],
                    'fulfillment_provider_id' => $line['fulfillment_provider_id'] ?? $fallbackProvider,
                    'supplier_product_id' => null,
                    'fulfillment_status' => 'pending',
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['price'],
                    'total' => $line['price'] * $line['quantity'],
                    'source_sku' => null,
                    'snapshot' => [
                        'name' => $line['name'],
                        'variant' => $line['variant'],
                    ],
                    'meta' => [
                        'media' => $line['media'],
                        'coupon_code' => $coupon['code'] ?? null,
                    ],
                ]);
            });

            $paymentProvider = in_array($data['payment_method'], ['card', 'mobile_money'], true)
                ? 'paystack'
                : $data['payment_method'];

            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => $paymentProvider,
                'status' => 'pending',
                'provider_reference' => null,
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'paid_at' => null,
                'meta' => [
                    'type' => 'checkout_pending',
                    'payment_method' => $data['payment_method'],
                    'coupon_code' => $coupon['code'] ?? null,
                ],
            ]);

            event(new OrderPlaced($order));

            return [$order, $customer, $payment];
        });

        if (in_array($data['payment_method'], ['card', 'mobile_money'], true)) {
            $reference = 'azr_' . strtolower($order->number) . '_' . Str::lower(Str::random(6));
            $payment->update(['provider_reference' => $reference]);

            try {
                $init = app(PaystackService::class)->initialize(
                    $order,
                    $payment,
                    ['email' => $data['email']],
                    $data['payment_method']
                );
            } catch (\Throwable $e) {
                return back()->withErrors([
                    'payment' => 'Unable to start payment. Please try again.',
                ]);
            }

            $authorizationUrl = $init->data['authorization_url'] ?? null;
            if (! $authorizationUrl) {
                return back()->withErrors([
                    'payment' => 'Payment provider did not return an authorization link.',
                ]);
            }

            session()->forget(['cart', 'cart_coupon']);
            app(AbandonedCartService::class)->markRecovered();

            return redirect()->away($authorizationUrl);
        }

        session()->forget(['cart', 'cart_coupon']);
        app(AbandonedCartService::class)->markRecovered();

        return redirect()->route('orders.confirmation', ['number' => $order->number]);
    }

    public function confirmation(string $number): Response
    {
        $order = Order::query()
            ->where('number', $number)
            ->with(['shippingAddress', 'billingAddress', 'orderItems'])
            ->firstOrFail();

        return Inertia::render('Orders/Confirmation', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'email' => $order->email,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'currency' => $order->currency,
                'grand_total' => $order->grand_total,
                'items' => $order->orderItems->map(fn ($item) => [
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
                ],
            ],
        ]);
    }

    private function subtotal(array $cart): float
    {
        return collect($cart)->reduce(function ($carry, $line) {
            return $carry + ((float) $line['price'] * (int) $line['quantity']);
        }, 0.0);
    }

    private function generateNumber(): string
    {
        do {
            $number = 'DS-' . Str::upper(Str::random(8));
        } while (Order::where('number', $number)->exists());

        return $number;
    }

    private function quoteShipping(array $cart, array $data): array
    {
        $fallback = [
            'shipping_total' => 0,
            'shipping_method' => 'standard',
        ];

        $providerId = $cart[0]['fulfillment_provider_id'] ?? SiteSetting::query()->value('default_fulfillment_provider_id');
        if (! $providerId) {
            return $fallback;
        }

        $provider = FulfillmentProvider::find($providerId);
        if (! $provider || $provider->driver_class !== \App\Domain\Fulfillment\Strategies\CJDropshippingFulfillmentStrategy::class) {
            return $fallback;
        }

        $destination = [
            'country' => strtoupper($data['country'] ?? 'CI'),
            'province' => $data['state'] ?? null,
            'city' => $data['city'] ?? null,
            'zip' => $data['postal_code'] ?? null,
        ];

        $items = collect($cart)->map(function ($line) {
            $variant = ProductVariant::find($line['variant_id']);
            $product = $variant?->product;
            return [
                'vid' => $line['cj_vid'] ?? $line['vid'] ?? null,
                'sku' => $line['external_sku'] ?? $variant?->sku ?? $line['sku'] ?? '',
                'quantity' => (int) $line['quantity'],
                            'warehouse_id' => $product?->cj_warehouse_id,
            ];
        })->filter(fn ($i) => $i['sku'] !== '')->values()->all();

        if (empty($items)) {
            return $fallback;
        }

        try {
            // Use the product's warehouse if available, otherwise fall back to provider default
            $warehouseId = collect($items)->pluck('warehouse_id')->filter()->first() 
                ?? $provider->settings['warehouse_id'] 
                ?? null;
            
            $options = app(CJFreightService::class)->quote($destination, $items, [
                'warehouseId' => $warehouseId,
                'logisticsType' => $provider->settings['logistics_type'] ?? null,
            ]);
            $first = $options[0] ?? null;
            if ($first) {
                return [
                    'shipping_total' => (float) ($first['price'] ?? 0),
                    'shipping_method' => $first['logisticName'] ?? ($provider->settings['shipping_method'] ?? 'standard'),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('CJ freight quote failed', ['error' => $e->getMessage()]);
        }

        return $fallback;
    }

    private function quotePayloadFromAddress(?Address $address): array
    {
        return [
            'country' => strtoupper($address?->country ?? 'CI'),
            'state' => $address?->state,
            'city' => $address?->city,
            'postal_code' => $address?->postal_code,
        ];
    }

    private function discount(array $cart, ?array $coupon): float
    {
        if (! $coupon) {
            return 0.0;
        }

        $subtotal = $this->subtotal($cart);
        if ($coupon['min_order_total'] && $subtotal < (float) $coupon['min_order_total']) {
            return 0.0;
        }

        if ($coupon['type'] === 'fixed') {
            return min((float) $coupon['amount'], $subtotal);
        }

        return round($subtotal * ((float) $coupon['amount'] / 100), 2);
    }

    private function calculateDiscounts(array $cart, ?array $coupon, ?Customer $customer, float $subtotal): array
    {
        $couponDiscount = $this->discount($cart, $coupon);
        $campaign = app(CampaignManager::class)->bestForCart($cart, $subtotal, $customer);

        if ($couponDiscount >= ($campaign['amount'] ?? 0)) {
            return [
                'amount' => $couponDiscount,
                'label' => $coupon ? ('Coupon: ' . ($coupon['code'] ?? '')) : null,
            ];
        }

        return [
            'amount' => $campaign['amount'] ?? 0.0,
            'label' => $campaign['label'] ?? null,
        ];
    }

    private function currentCustomer(): ?Customer
    {
        $user = Auth::guard('customer')->user();
        if (! $user) {
            return null;
        }

        return Customer::find($user->id);
    }

    private function calculateTax(float $amount, ?SiteSetting $settings): float
    {
        $rate = (float) ($settings?->tax_rate ?? 0);
        if ($rate <= 0) {
            return 0.0;
        }

        return round($amount * ($rate / 100), 2);
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

    private function validateStock(array $cart): bool
    {
        foreach ($cart as $line) {
            // Check local stock first
            if (array_key_exists('stock_on_hand', $line) && is_numeric($line['stock_on_hand'])) {
                if ((int) $line['stock_on_hand'] < (int) $line['quantity']) {
                    return false;
                }
                continue; // local stock sufficient, skip CJ check
            }

            // Fallback to CJ API check
            if (! $this->validateCjStock([$line])) {
                return false;
            }
        }

        return true;
    }

    private function validateCjStock(array $cart): bool
    {
        $client = app(CJDropshippingClient::class);

        foreach ($cart as $line) {
            // Prefer local stock snapshot if present
            if (array_key_exists('stock_on_hand', $line) && is_numeric($line['stock_on_hand'])) {
                if ((int) $line['stock_on_hand'] < (int) $line['quantity']) {
                    return false;
                }
                continue;
            }

            try {
                if ($line['cj_vid'] ?? false) {
                    $resp = $client->getStockByVid((string) $line['cj_vid']);
                } elseif ($line['sku'] ?? false) {
                    $resp = $client->getStockBySku((string) $line['sku']);
                } elseif ($line['cj_pid'] ?? false) {
                    $resp = $client->getStockByPid((string) $line['cj_pid']);
                } else {
                    continue;
                }

                $available = $this->sumStorage($resp->data ?? null);
                if ($available < (int) $line['quantity']) {
                    return false;
                }
            } catch (ApiException $exception) {
                Log::warning('CJ stock check failed during checkout', ['error' => $exception->getMessage(), 'line' => $line['id'] ?? null]);
                if (! $this->fallbackStockCheck($line, (int) $line['quantity'])) {
                    return false;
                }
                continue;
            } catch (\Throwable $exception) {
                Log::error('CJ stock check failed during checkout', ['error' => $exception->getMessage(), 'line' => $line['id'] ?? null]);
                if (! $this->fallbackStockCheck($line, (int) $line['quantity'])) {
                    return false;
                }
                continue;
            }
        }

        return true;
    }

    private function sumStorage(mixed $payload): int
    {
        $total = 0;

        $add = function ($value) use (&$total) {
            if (is_numeric($value)) {
                $total += (int) $value;
            }
        };

        if (is_numeric($payload)) {
            $add($payload);
            return $total;
        }

        if (is_array($payload)) {
            if (array_key_exists('storageNum', $payload)) {
                $add($payload['storageNum']);
            }

            foreach ($payload as $entry) {
                if (is_array($entry) && array_key_exists('storageNum', $entry)) {
                    $add($entry['storageNum']);
                } elseif (is_array($entry)) {
                    foreach ($entry as $deep) {
                        if (is_array($deep) && array_key_exists('storageNum', $deep)) {
                            $add($deep['storageNum']);
                        }
                    }
                }
            }
        }

        return $total;
    }

    private function fallbackStockCheck(array $line, int $desiredQty): bool
    {
        if (array_key_exists('stock_on_hand', $line) && is_numeric($line['stock_on_hand'])) {
            return (int) $line['stock_on_hand'] >= $desiredQty;
        }

        return true;
    }
}
