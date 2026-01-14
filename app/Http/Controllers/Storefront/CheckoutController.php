<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

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
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;
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
    public function getCartWithItems()
    {
        $data['cart'] = Cart::query()->where('user_id', \auth('customer')->id())
            ->orWhere('session_id', session()->id())
            ->with('items')
            ->first();

        $data['cart_items'] = $data['cart']?->items;
        if (!$data['cart'] || !$data['cart_items'] || !$data['cart_items']->count()) {
            return redirect()->route('products.index');
        }
        return $data;
    }

    public function index(): Response|RedirectResponse
    {

        $result = $this->getCartWithItems();

        if ($result instanceof RedirectResponse) {
            return $result;
        }
        $cart = $result['cart'];
        $cart_items = $result['cart_items'];


//        if (!$this->validateStock($cart)) {
//            return back()->withErrors(['cart' => 'One or more items are out of stock. Please adjust your cart.']);
//        }

        $subtotal = $cart->subTotal();
        $shipping = $cart->calculateShippingFees();

        $customer = auth('customer')->user();

        app(AbandonedCartService::class)->capture($cart_items->toArray(), $customer?->email, $customer?->id);

        $defaultAddress = isset($customer) ? $customer?->addresses()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first() : null;

        $quotePayload = $this->createQuotePayloadFromAddress($defaultAddress);

        $selectedMethod = $shippingQuote['shipping_method'] ?? 'standard';
        $coupon = session('cart_coupon');
        $discounts = $this->calculateDiscounts($cart, $cart_items, $coupon, $customer, $subtotal);
        $discount = $discounts['amount'];

        $settings = SiteSetting::query()->first();

        $taxTotal = calculateTax(max(0, $subtotal - $discount), @$settings->tax_rate);
        $taxIncluded = (bool)($settings?->tax_included ?? false);
        $total = $subtotal + $shipping - $discount + ($taxIncluded ? 0 : $taxTotal);

        return Inertia::render('Checkout/Index', [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'discount' => $discount,
            'coupon' => $coupon,
            'discount_label' => @$discounts['label'],
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
            'accept_terms' => ['accepted'],
        ]);

        $customer = auth('customer')->user();
        $subtotal = $cart->subTotal();
        $shipping = $cart->calculateShippingFees();


        $coupon = session('cart_coupon');
        $discounts = $this->calculateDiscounts($cart, $cart_items, $coupon, $customer, $subtotal);
        $discount = @$discounts['amount'] ?? 0;
        $settings = SiteSetting::query()->first();
        $shippingTotal = $this->applyShippingRules($shipping, $subtotal, $discount, $settings);
        $taxTotal = calculateTax(max(0, $subtotal - $discount), $settings->tax_rate);
        $taxIncluded = (bool)($settings?->tax_included ?? false);
        $grandTotal = $subtotal + $shippingTotal - $discount + ($taxIncluded ? 0 : $taxTotal);

        [$order, $payment] = DB::transaction(function () use ($validatedData, $cart, $cart_items, $discount, $coupon, $subtotal, $shippingTotal, $taxTotal, $grandTotal) {
            $customer = Auth::guard('customer')->user();
            $isGuest = !$customer;

            // Create shipping address
            $shippingAddress = Address::create([
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
            $order = Order::query()->create([
                'number' => Order::generateOrderNumber(),
                'user_id' => null,
                'customer_id' => $customer?->id,
                'guest_name' => $isGuest ? trim($validatedData['first_name'] . ' ' . ($validatedData['last_name'] ?? '')) : null,
                'guest_phone' => $isGuest ? $validatedData['phone'] : null,
                'is_guest' => $isGuest,
                'email' => $validatedData['email'],
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
                'delivery_notes' => $validatedData['delivery_notes'] ?? null,
                'coupon_code' => $coupon['code'] ?? null,
                'placed_at' => now(),
            ]);

            // Create order items
            $fallbackProvider = SiteSetting::query()->value('default_fulfillment_provider_id');

            foreach ($cart_items as $line) {
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
                        'name' => @$line?->product['name'],
                        'variant' => @$line?->variant['title'],
                    ],
                    'meta' => [
                        'media' => $line['media'] ?? null,
                        'coupon_code' => $coupon['code'] ?? null,
                    ],
                ]);
            }

            foreach ($cart->shippings as $shipping) {
                $shipping = $shipping->toArray();
                $shipping['order_id'] = $order->id;
                OrderShipping::query()->create($shipping);
            }

            // Create payment
            $paymentProvider = in_array($validatedData['payment_method'], ['card', 'mobile_money'], true)
                ? 'paystack'
                : $validatedData['payment_method'];

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
                    'payment_method' => $validatedData['payment_method'],
                    'coupon_code' => $coupon['code'] ?? null,
                ],
            ]);

            event(new OrderPlaced($order));

            return [$order, $payment];
        });

        // Handle payment based on method
        if (in_array($validatedData['payment_method'], ['card', 'mobile_money'], true)) {
            $reference = 'azr_' . strtolower($order->number) . '_' . Str::lower(Str::random(6));
            $payment->update(['provider_reference' => $reference]);

            try {
                $init = app(PaystackService::class)->initialize(
                    $order,
                    $payment,
                    ['email' => $validatedData['email']],
                    $validatedData['payment_method']
                );

                $authorizationUrl = $init->data['authorization_url'] ?? null;

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

        return Inertia::render('Orders/Confirmation', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'email' => $order->email,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'currency' => $order->currency,
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
                ],
            ],
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
        $couponDiscount = $cart->discount($coupon);
        $cart_items = (CartResource::collection($cart_items))->jsonSerialize();
        $campaign = app(CampaignManager::class)->bestForCart($cart_items, $subtotal, $customer);

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

        // Check for direct storageNum
        if (isset($payload['storageNum']) && is_numeric($payload['storageNum'])) {
            $total += (int)$payload['storageNum'];
        }

        // Recursively search for storageNum in nested arrays
        array_walk_recursive($payload, function ($value) use (&$total) {
            if (is_numeric($value)) {
                $total += (int)$value;
            }
        });

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
}
