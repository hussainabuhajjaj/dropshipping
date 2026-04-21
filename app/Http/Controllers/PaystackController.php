<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Common\Models\Address;
use App\Domain\Payments\PaymentService;
use App\Infrastructure\Payments\Paystack\PaystackService;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Api\ApiException;
use App\Services\Currency\CurrencyConversionService;
use App\Support\ResolvesStorefrontVariantLabels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PaystackController extends Controller
{
    use ResolvesStorefrontVariantLabels;

    public function __construct(
        private readonly PaystackService $paystackService,
        private readonly PaymentService $paymentService,
    ) {
    }

    public function initialize(Request $request): JsonResponse
    {
        $payload = [
            'payment_type' => $request->input('payment_type', $request->input('payment_method')),
            'customer_email' => $request->input('customer_email', $request->input('email')),
            'customer_name' => $request->input('customer_name'),
            'phone' => $request->input('phone'),
            'mobile_provider' => $request->input('mobile_provider', $request->input('provider')),
            'grand_total' => $request->input('grand_total', $request->input('cart_data.total')),
            'currency' => $request->input('currency', $request->input('cart_data.currency')),
            'return_url' => $request->input('return_url'),
            // Address fields
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'line1' => $request->input('line1'),
            'line2' => $request->input('line2'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'postal_code' => $request->input('postal_code'),
            'country' => $request->input('country', 'CI'),
        ];

        $validator = Validator::make($payload, [
            'payment_type' => ['required', 'in:card,mobile_money'],
            'customer_email' => ['required', 'email'],
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile_provider' => ['nullable', 'string', 'max:50', Rule::in(['mtn', 'orange', 'wave'])],
            'grand_total' => ['required', 'numeric', 'min:1'],
            'currency' => ['required', 'string', 'in:XOF'],
            'return_url' => ['nullable', 'url', 'max:2048'],
            // Address validation
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'line1' => ['nullable', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:2'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $validated = $validator->validated();
            
            // Use the exact grand_total from frontend (already in XOF)
            $amount = (int) round((float) $validated['grand_total']);
            
            [$customer, $cart, $cartSummary, $shipping, $subtotal] = $this->resolveCartCheckoutContext();

            Log::info('Paystack payment initialization', [
                'frontend_grand_total' => (float) $validated['grand_total'],
                'amount_to_charge' => $amount,
                'cart_total' => $cartSummary['total'] ?? null,
                'cart_currency' => $cartSummary['currency'] ?? null,
                'shipping' => $shipping,
                'subtotal' => $subtotal,
            ]);

            // Create order and payment during initialization
            [$order, $payment] = $this->createOrReusePendingPayment(
                $customer->id,
                $validated['customer_email'],
                $validated['customer_name'],
                $amount,
                $validated['payment_type'],
                $validated['phone'] ?? null,
                $validated['mobile_provider'] ?? null,
                [
                    'first_name' => $validated['first_name'] ?? null,
                    'last_name' => $validated['last_name'] ?? null,
                    'line1' => $validated['line1'] ?? null,
                    'line2' => $validated['line2'] ?? null,
                    'city' => $validated['city'] ?? null,
                    'state' => $validated['state'] ?? null,
                    'postal_code' => $validated['postal_code'] ?? null,
                    'country' => $validated['country'] ?? 'CI',
                ],
                $shipping,
                $subtotal,
                $cart
            );

            if ($validated['payment_type'] === 'card') {
                $result = $this->paystackService->initializeTransaction(
                    $order,
                    $payment,
                    $validated['customer_email'],
                    $validated['customer_name'],
                    $validated['return_url'] ?? route('payments.paystack.callback')
                );

                return response()->json([
                    'status' => true,
                    'data' => [
                        'authorization_url' => $result['authorization_url'],
                        'access_code' => $result['access_code'],
                        'reference' => $result['reference'],
                    ],
                ]);
            }

            // Handle mobile money
            $result = $this->paystackService->initializeMobileMoneyTransaction(
                $order,
                $payment,
                $validated['customer_email'],
                $validated['customer_name'],
                $validated['phone'],
                $validated['mobile_provider']
            );

            return response()->json([
                'status' => true,
                'data' => [
                    'reference' => $result['reference'],
                    'message' => 'Mobile money payment initiated',
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Paystack initialization failed', [
                'error' => $exception->getMessage(),
                'payload' => $payload,
            ]);

            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        $reference = (string) ($request->query('reference') ?: $request->query('trxref', ''));

        if ($reference === '') {
            return redirect()->route('home')->with('error', 'Payment reference not found.');
        }

        try {
            $payment = $this->paymentService->verifyPaystack($reference)->load('order');

            if ($payment->status === 'paid' && $payment->order) {
                return redirect()
                    ->route('orders.confirmation', ['number' => $payment->order->number])
                    ->with('status', 'Payment confirmed.');
            }

            return redirect()
                ->route('orders.confirmation', ['number' => $payment->order?->number ?? ''])
                ->with('error', 'Payment is still pending confirmation.');
        } catch (\Throwable $exception) {
            Log::error('Paystack callback failed', [
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);

            return redirect()->route('home')->with('error', 'Payment verification failed.');
        }
    }

    private function resolveCartCheckoutContext(): array
    {
        $customer = auth('customer')->user();

        if (! $customer) {
            throw new \RuntimeException('Customer not authenticated.');
        }

        $cart = Cart::query()
            ->where('user_id', $customer->id)
            ->with(['items', 'shippings'])
            ->first();

        if (! $cart || ! $cart->items || $cart->items->isEmpty()) {
            throw new \RuntimeException('Cart is empty.');
        }

        $cart_items = $cart->items;
        $summary = $cart->getSummery();

        // Calculate shipping from cart
        $shippingQuote = $cart->quoteShippingForItems($cart_items, true);
        $shipping = (float) ($shippingQuote['total'] ?? 0);

        $subtotal = $cart->subTotal();
        $total = (float) ($summary['total'] ?? 0);
        $currency = strtoupper((string) ($summary['currency'] ?? 'XOF'));

        if ($total <= 0) {
            throw new \RuntimeException('Cart total is invalid.');
        }

        // No currency conversion needed - everything is in XOF

        return [$customer, $cart, $summary, $shipping, $subtotal];
    }

    private function createPendingPayment(
        int $customerId,
        string $email,
        string $customerName,
        int $amount,
        string $paymentType,
        ?string $phone,
        ?string $mobileProvider,
        array $meta = []
    ): Payment {
        return Payment::query()->create([
            'order_id' => null, // No order yet
            'provider' => 'paystack',
            'status' => 'pending',
            'provider_reference' => 'pstk_pending_' . strtolower(\Illuminate\Support\Str::random(12)),
            'amount' => $amount,
            'currency' => 'XOF',
            'meta' => array_merge([
                'payment_type' => $paymentType,
                'customer_email' => $email,
                'customer_name' => $customerName,
                'payment_phone' => $phone,
                'mobile_provider' => $mobileProvider,
                'customer_id' => $customerId,
            ], $meta),
        ]);
    }

    private function createOrReusePendingPayment(
        int $customerId,
        string $email,
        string $customerName,
        int $amount,
        string $paymentType,
        ?string $phone,
        ?string $mobileProvider,
        array $addressData = [],
        ?float $shipping = null,
        ?float $subtotal = null,
        ?\App\Models\Cart $cart = null
    ): array {
        $payment = Payment::query()
            ->where('provider', 'paystack')
            ->where('status', 'pending')
            ->whereJsonContains('meta->customer_id', $customerId)
            ->latest('id')
            ->first();

        if ($payment && $payment->order) {
            // Generate a new reference to avoid duplicates
            $newReference = 'pstk_' . strtolower($payment->order->number) . '_' . strtolower(\Illuminate\Support\Str::random(8)) . '_' . time();
            
            $payment->update([
                'provider_reference' => $newReference,
            ]);
        }

        if (! $payment) {
            $address = Address::query()->create([
                'customer_id' => $customerId,
                'name' => trim(($addressData['first_name'] ?? '') . ' ' . ($addressData['last_name'] ?? '')) ?: $customerName,
                'phone' => $phone,
                'line1' => $addressData['line1'] ?? 'Address not provided',
                'line2' => $addressData['line2'] ?? null,
                'city' => $addressData['city'] ?? 'City not provided',
                'state' => $addressData['state'] ?? null,
                'postal_code' => $addressData['postal_code'] ?? null,
                'country' => strtoupper($addressData['country'] ?? 'CI'),
                'type' => 'shipping',
                'is_default' => false,
            ]);

            $order = Order::createWithGeneratedNumber([
                'user_id' => null,
                'customer_id' => $customerId,
                'is_guest' => false,
                'email' => $email,
                'locale' => app()->getLocale(),
                'status' => 'pending',
                'payment_status' => 'pending',
                'currency' => 'XOF',
                'subtotal' => $subtotal ?? $amount,
                'shipping_total' => $shipping ?? 0,
                'shipping_total_estimated' => $shipping ?? 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'grand_total' => ($subtotal ?? $amount) + ($shipping ?? 0),
                'discount_snapshot' => null,
                'discount_source' => null,
                'shipping_address_id' => $address->id,
                'billing_address_id' => $address->id,
                'shipping_method' => 'standard',
                'delivery_notes' => null,
                'coupon_code' => null,
                'placed_at' => now(),
            ]);

            // Create order items from cart
            $fallbackProvider = \App\Models\SiteSetting::query()->value('default_fulfillment_provider_id');

            if ($cart) {
                $cart_items = $cart->items;

                foreach ($cart_items as $line) {
                    $providerId = $line['fulfillment_provider_id'] ?? $fallbackProvider;
                    $supplierProduct = \App\Domain\Products\Models\SupplierProduct::query()
                        ->where('product_variant_id', $line['variant_id'])
                        ->when($providerId, fn ($query) => $query->where('fulfillment_provider_id', $providerId))
                        ->first();

                    // No currency conversion needed - everything is in XOF
                    $unitPrice = $line->getSinglePrice();
                    $totalPrice = $unitPrice * $line['quantity'];

                    \App\Domain\Orders\Models\OrderItem::create([
                        'order_id' => $order->id,
                        'product_variant_id' => $line['variant_id'],
                        'fulfillment_provider_id' => $providerId,
                        'supplier_product_id' => $supplierProduct?->id,
                        'fulfillment_status' => 'pending',
                        'quantity' => $line['quantity'],
                        'unit_price' => $unitPrice,
                        'total' => $totalPrice,
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
                            'supplier_type' => $line->product?->supplier_type,
                            'supplier_product_id' => $supplierProduct?->id,
                            'external_product_id' => $supplierProduct?->external_product_id,
                            'external_sku' => $supplierProduct?->external_sku,
                        ],
                    ]);
                }
            }

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'provider' => 'paystack',
                'status' => 'pending',
                'provider_reference' => 'pstk_' . strtolower($order->number) . '_' . strtolower(\Illuminate\Support\Str::random(6)),
                'amount' => ($subtotal ?? $amount) + ($shipping ?? 0),
                'currency' => 'XOF',
                'meta' => [
                    'payment_type' => $paymentType,
                    'customer_email' => $email,
                    'customer_name' => $customerName,
                    'payment_phone' => $phone,
                    'mobile_provider' => $mobileProvider,
                    'customer_id' => $customerId,
                ],
            ]);

            // Clear cart after order creation
            if ($cart) {
                $cart->items()->delete();
                $cart->shippings()->delete();
                $cart->delete();
            }

            return [$order, $payment];
        }

        return [$payment->order, $payment];
    }
}
