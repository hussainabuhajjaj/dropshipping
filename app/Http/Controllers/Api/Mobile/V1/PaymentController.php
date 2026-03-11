<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Domain\Payments\PaymentService;
use App\Http\Requests\Api\Mobile\V1\Payments\KorapayInitRequest;
use App\Http\Requests\Api\Mobile\V1\Payments\KorapayVerifyRequest;
use App\Http\Resources\Mobile\V1\KorapayInitResource;
use App\Http\Resources\Mobile\V1\PaymentStatusResource;
use App\Http\Resources\Mobile\V1\PaymentMethodsResource;
use App\Models\Payment;
use App\Domain\Orders\Models\Order;
use App\Models\Cart;
use App\Models\PaymentMethod;
use App\Services\Payments\KorapayService;
use App\Services\Payments\PaymentResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends ApiController
{
    public function init(KorapayInitRequest $request, KorapayService $korapayService): JsonResponse
    {
        // Debug: Log the incoming request data
        \Log::info('Payment request received', [
            'all_data' => $request->all(),
            'validated' => $request->validated(),
        ]);
        
        $data = $request->validated();

        // Use exact storefront logic - getItem and getSummery
        $item = $this->getItem($request);
        if (!$item) {
            return $this->error('You must log in first to continue shopping.', 401);
        }
        if ($item->items->isEmpty()) {
            return $this->error('Cart is empty', 422);
        }

        // Use exact storefront summery calculation
        $summery = [];
        if ($item instanceof Cart) {
            $summery = $item->getSummery();
        }

        $final_total = $summery['total'] ?? 0;
        if ((float) $final_total <= 0) {
            return $this->error('Cart total must be greater than zero', 422);
        }
        
        // Debug logging
        \Log::info('Payment initialization debug', [
            'cart_id' => $item->id,
            'cart_items_count' => $item->items->count(),
            'cart_items' => $item->items->map(function($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'line_total' => $item->line_total,
                    'price' => $item->price,
                ];
            })->toArray(),
            'summery' => $summery,
            'final_total' => $final_total,
            'cart_subtotal' => $item->subTotal(),
        ]);

        // Note: Minimum cart validation should be handled in cart/checkout UI, not during payment
        // The mobile cart API already returns minimum_cart_requirement for UI validation

        // Validate method (same as storefront)
        $method = $data['method'] ?? 'card';
        if (!in_array($method, ['card', 'mobile_money'])) {
            return $this->error('Invalid payment method');
        }

        \Log::info('Payment method selected', [
            'method' => $method,
            'amount' => $final_total,
        ]);

        // Use KorapayService directly like storefront
        $checkout = $korapayService->initializePayment($final_total, $method);

        if (isset($checkout['data'])) {
            // Store reference for verification (like storefront)
            session()->put('reference', $checkout['data']['reference']);

            return $this->success([
                'reference' => $checkout['data']['reference'],
                'redirect' => $checkout['data']['checkout_url'],
                'checkout_url' => $checkout['data']['checkout_url'],
                'amount' => (float) $final_total,
                'currency' => $method === 'mobile_money' ? 'XOF' : 'USD',
                'method' => $method,
            ]);
        }

        return $this->error('Payment initialization failed');
    }

    public function verify(KorapayVerifyRequest $request, PaymentService $paymentService): JsonResponse
    {
        $validated = $request->validated();
        $customer = $request->user();

        if (! empty($validated['reference'])) {
            $payment = $paymentService->verifyKorapay((string) $validated['reference']);
        } else {
            $order = Order::query()
                ->where('number', (string) ($validated['order_number'] ?? ''))
                ->first();

            if (! $order || $order->customer_id !== $customer?->id) {
                return $this->notFound('Order not found');
            }

            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->where('provider', 'korapay')
                ->latest('id')
                ->first();

            if (! $payment) {
                return $this->notFound('Payment not found');
            }
        }

        return $this->success(new PaymentStatusResource([
            'payment_status' => $payment->status,
            'order_status' => $payment->order?->status,
            'order_number' => $payment->order?->number,
            'reference' => $payment->provider_reference,
            'is_paid' => $payment->status === 'paid',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toISOString(),
        ]));
    }

    /**
     * Get available payment methods for mobile
     */
    public function methods(Request $request): JsonResponse
    {
        $customer = $request->user();
        
        $paymentMethods = PaymentMethod::query()
            ->where('customer_id', $customer?->id)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(PaymentMethodsResource::collection($paymentMethods));
    }

    /**
     * Unified payment initialization for mobile (matching storefront exactly)
     */
    public function initialize(Request $request, KorapayService $korapayService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string|max:64',
            'method' => 'required|in:card,mobile_money',
            'customer' => 'nullable|array',
            'customer.email' => 'nullable|email',
            'customer.name' => 'nullable|string|max:255',
            'customer.phone' => 'nullable|string|max:20',
            'return_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $customer = $request->user();
        $method = $data['method'];

        $order = Order::query()
            ->where('number', $data['order_number'])
            ->first();

        if (!$order || $order->customer_id !== $customer?->id) {
            return $this->notFound('Order not found');
        }

        // Check if order is already paid
        if ($order->payment_status === 'paid') {
            return $this->error('Order is already paid', 400);
        }

        try {
            // Use the exact storefront KorapayService flow
            $checkout = $korapayService->initializePayment($order->grand_total, $method);

            if (!isset($checkout['data'])) {
                return $this->error('Payment provider did not return valid response', 500);
            }

            // Store reference in session (like storefront)
            session(['reference' => $checkout['data']['reference']]);

            return $this->success([
                'status' => true,
                'data' => [
                    'redirect' => $checkout['data']['checkout_url']
                ],
                'reference' => $checkout['data']['reference'],
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'order_number' => $order->number,
                'method' => $method,
            ]);
        } catch (\Exception $e) {
            \Log::error('Mobile payment initialization failed', [
                'order_number' => $order->number,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Payment initialization failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Payment redirect handler (matching storefront exactly)
     */
    public function redirect(Request $request, PaymentResultService $paymentResultService): JsonResponse
    {
        $reference = (string) ($request->input('reference')
            ?? $request->input('payment_reference')
            ?? $request->input('transaction_reference')
            ?? $request->query('trxref')
            ?? '');

        if (!$reference) {
            return $this->error('Missing payment reference', 404);
        }

        try {
            $korapayService = app(KorapayService::class);
            $verifyResult = $korapayService->checkStatus($reference);

            if (!isset($verifyResult) || !$verifyResult['status']) {
                return $this->error('Payment verification failed', 400);
            }

            $paymentResult = strtolower((string) ($verifyResult['data']['status'] ?? ''));

            if ($paymentResult === 'success') {
                // Find existing payment
                $existingPayment = Payment::query()
                    ->where('provider', 'korapay')
                    ->where('provider_reference', $reference)
                    ->with('order')
                    ->latest('id')
                    ->first();

                if ($existingPayment?->order) {
                    return $this->success([
                        'status' => 'success',
                        'redirect' => '/orders/confirmation/' . $existingPayment->order->number,
                        'order_number' => $existingPayment->order->number,
                        'message' => 'Payment confirmed successfully',
                    ]);
                }

                return $this->success([
                    'status' => 'success',
                    'message' => 'Payment processed successfully',
                ]);
            } else {
                return $this->success([
                    'status' => 'failed',
                    'message' => 'Payment failed or cancelled',
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Mobile payment redirect failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Payment redirect failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Unified payment verification for mobile
     */
    public function verifyPayment(
        Request $request,
        PaymentService $paymentService,
        KorapayService $korapayService,
        PaymentResultService $paymentResultService
    ): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'nullable|string|max:255',
            'order_number' => 'nullable|string|max:64',
            'provider' => 'nullable|in:korapay,stripe,paypal',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $customer = $request->user();

        if (!empty($data['reference'])) {
            $reference = (string) $data['reference'];
            $provider = (string) ($data['provider'] ?? 'korapay');

            $existingPayment = Payment::query()
                ->where('provider', $provider)
                ->where('provider_reference', $reference)
                ->latest('id')
                ->first();

            if ($existingPayment) {
                $payment = $existingPayment;
            } else {
                try {
                    $payment = match ($provider) {
                        'korapay' => $paymentService->verifyKorapay($reference),
                        default => throw new \InvalidArgumentException("Payment provider '{$provider}' is not supported"),
                    };
                } catch (\RuntimeException $exception) {
                    if ($provider !== 'korapay' || !str_contains($exception->getMessage(), 'Order number missing in webhook payload')) {
                        throw $exception;
                    }

                    // Fallback for cart-first mobile flow where Korapay verify payload may miss order metadata.
                    $verifyResult = $korapayService->checkStatus($reference);
                    $paymentStatus = strtolower((string) ($verifyResult['data']['status'] ?? ''));

                    if ($paymentStatus === 'success') {
                        $item = $this->getItem($request);
                        if ($item) {
                            try {
                                $paymentResultService->registerCompletePayment($item, $verifyResult);
                            } catch (\Throwable $e) {
                                \Log::warning('Mobile verify fallback registerCompletePayment failed', [
                                    'reference' => $reference,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }

                        $payment = Payment::query()
                            ->where('provider', 'korapay')
                            ->where('provider_reference', $reference)
                            ->latest('id')
                            ->first();

                        if (! $payment) {
                            return $this->success(new PaymentStatusResource([
                                'payment_status' => 'paid',
                                'order_status' => null,
                                'order_number' => null,
                                'reference' => $reference,
                                'is_paid' => true,
                                'amount' => $verifyResult['data']['amount_paid'] ?? null,
                                'currency' => $verifyResult['data']['currency'] ?? null,
                                'paid_at' => now()->toISOString(),
                            ]));
                        }
                    } else {
                        return $this->success(new PaymentStatusResource([
                            'payment_status' => $paymentStatus ?: 'failed',
                            'order_status' => null,
                            'order_number' => null,
                            'reference' => $reference,
                            'is_paid' => false,
                            'amount' => $verifyResult['data']['amount_paid'] ?? null,
                            'currency' => $verifyResult['data']['currency'] ?? null,
                            'paid_at' => null,
                        ]));
                    }
                }
            }
        } else {
            $order = Order::query()
                ->where('number', $data['order_number'] ?? '')
                ->first();

            if (!$order || $order->customer_id !== $customer?->id) {
                return $this->notFound('Order not found');
            }

            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->when($data['provider'], fn ($query, $provider) => $query->where('provider', $provider))
                ->latest('id')
                ->first();

            if (!$payment) {
                return $this->notFound('Payment not found');
            }
        }

        return $this->success(new PaymentStatusResource([
            'payment_status' => $payment->status,
            'order_status' => $payment->order?->status,
            'order_number' => $payment->order?->number,
            'reference' => $payment->provider_reference,
            'is_paid' => in_array(strtolower((string) $payment->status), ['paid', 'success', 'succeeded', 'captured'], true),
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toISOString(),
        ]));
    }

    private function getItem(Request $request): ?Cart
    {
        $customer = $request->user();
        if (! $customer) {
            return null;
        }

        return Cart::query()
            ->where('user_id', $customer->id)
            ->orderByDesc('updated_at')
            ->with('items')
            ->first();
    }
}
