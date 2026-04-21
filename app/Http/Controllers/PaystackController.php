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

            // Create only a payment record, not the order yet
            $payment = $this->createPendingPayment(
                $customer->id,
                $validated['customer_email'],
                $validated['customer_name'],
                $amount,
                $validated['payment_type'],
                $validated['phone'] ?? null,
                $validated['mobile_provider'] ?? null,
                [
                    'cart_id' => $cart->id,
                    'cart_summary' => $cartSummary,
                    'address' => [
                        'first_name' => $validated['first_name'] ?? null,
                        'last_name' => $validated['last_name'] ?? null,
                        'line1' => $validated['line1'] ?? null,
                        'line2' => $validated['line2'] ?? null,
                        'city' => $validated['city'] ?? null,
                        'state' => $validated['state'] ?? null,
                        'postal_code' => $validated['postal_code'] ?? null,
                        'country' => $validated['country'] ?? 'CI',
                    ],
                    'shipping' => $shipping,
                    'subtotal' => $subtotal,
                ]
            );

            if ($validated['payment_type'] === 'card') {
                $result = $this->paystackService->initializeTransaction(
                    null, // No order yet
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
                null, // No order yet
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
}
