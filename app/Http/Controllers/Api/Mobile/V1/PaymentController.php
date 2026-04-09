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
    public function init(KorapayInitRequest $request, PaymentService $paymentService): JsonResponse
    {
        // Legacy endpoint: previously initialized Korapay without creating an Order/Payment record,
        // which means we cannot reconcile successful payments if redirect/webview close happens.
        // Prefer the unified flow: `POST /api/mobile/v1/checkout/confirm` then `POST /api/mobile/v1/payments/initialize`.
        if ($request->filled('order_number')) {
            return $this->initialize($request, $paymentService);
        }

        return $this->error(
            'Deprecated endpoint. Please use checkout/confirm then payments/initialize (redirect is optional; webhook/verify will confirm).',
            410
        );
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
    public function initialize(Request $request, PaymentService $paymentService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string|max:64',
            'method' => 'required|in:card,mobile_money',
            'customer' => 'nullable|array',
            'customer.email' => 'nullable|email',
            'customer.name' => 'nullable|string|max:255',
            'customer.phone' => 'nullable|string|max:20',
            'return_url' => 'nullable|string|max:2048',
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

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('provider', 'korapay')
            ->latest('id')
            ->first();

        if (! $payment) {
            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'provider' => 'korapay',
                'status' => 'pending',
                'provider_reference' => null,
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'paid_at' => null,
                'meta' => [
                    'type' => 'checkout_pending',
                    'payment_method' => $method,
                    'created_by' => 'mobile_initialize',
                ],
            ]);
        }

        try {
            $checkout = $paymentService->initializeKorapay(
                $order,
                $payment,
                [
                    'email' => $data['customer']['email'] ?? $order->email,
                    'name' => $data['customer']['name'] ?? $customer?->name ?? 'Customer',
                    'phone' => $data['customer']['phone'] ?? null,
                ],
                $method,
                $data['return_url'] ?? null
            );

            if (! isset($checkout['checkout_url'])) {
                return $this->error('Payment provider did not return valid response', 500);
            }

            session(['reference' => $checkout['reference']]);

            return $this->success([
                'reference' => $checkout['reference'],
                'redirect' => $checkout['checkout_url'],
                'checkout_url' => $checkout['checkout_url'],
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
    public function redirect(
        Request $request,
        PaymentResultService $paymentResultService,
        PaymentService $paymentService
    ): JsonResponse
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
            // Track redirects so we can distinguish "paid via webhook/verify but never redirected back".
            $existing = Payment::query()
                ->where('provider', 'korapay')
                ->where('provider_reference', $reference)
                ->latest('id')
                ->first();

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

            $korapayService = app(KorapayService::class);
            $verifyResult = $korapayService->checkStatus($reference);

            if (!isset($verifyResult) || !$verifyResult['status']) {
                return $this->error('Payment verification failed', 400);
            }

            $paymentResult = strtolower((string) ($verifyResult['data']['status'] ?? ''));

            if ($paymentResult === 'success') {
                $existingPayment = $paymentService->verifyKorapay($reference)->load('order');

                if ($existingPayment?->order) {
                    return $this->success([
                        'status' => 'success',
                        'redirect' => '/orders/confirmation/' . $existingPayment->order->number,
                        'order_number' => $existingPayment->order->number,
                        'payment_status' => $existingPayment->status,
                        'order_status' => $existingPayment->order->status,
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

            if ($existingPayment && in_array(strtolower((string) $existingPayment->status), ['paid', 'success', 'succeeded', 'captured'], true)) {
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
