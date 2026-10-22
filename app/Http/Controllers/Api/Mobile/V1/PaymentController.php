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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends ApiController
{
    public function init(KorapayInitRequest $request, PaymentService $paymentService): JsonResponse
    {
        if ($request->filled('order_number')) {
            return $this->initialize($request, $paymentService);
        }

        return $this->error('Deprecated endpoint. Please use checkout/confirm then payments/initialize.', 410);
    }

    public function verify(KorapayVerifyRequest $request, PaymentService $paymentService): JsonResponse
    {
        $validated = $request->validated();
        $customer = $request->user();

        $payment = ! empty($validated['reference'])
            ? $paymentService->verifyPaystack((string) $validated['reference'])
            : $this->findOrderPayment($validated, $customer);

        if (! $payment) {
            return $this->notFound('Payment not found');
        }

        return $this->paymentStatusResponse($payment);
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
            'customer.mobile_provider' => 'nullable|string|max:50',
            'return_url' => 'nullable|string|max:2048',
            'meta_ads' => 'nullable|array',
            'meta_ads.platform' => 'nullable|in:ios,android',
            'meta_ads.advertiser_tracking_enabled' => 'nullable|boolean',
            'meta_ads.application_tracking_enabled' => 'nullable|boolean',
            'meta_ads.anon_id' => 'nullable|string|max:255',
            'meta_ads.madid' => 'nullable|string|max:255',
            'meta_ads.extinfo' => 'nullable|array',
            'meta_ads.extinfo.*' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $customer = $request->user();
        $method = $data['method'];
        $metaAds = is_array($data['meta_ads'] ?? null) ? $data['meta_ads'] : null;

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
            ->where('provider', 'paystack')
            ->latest('id')
            ->first();

        if (! $payment) {
            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'provider' => 'paystack',
                'status' => 'pending',
                'provider_reference' => null,
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'paid_at' => null,
                'meta' => [
                    'type' => 'checkout_pending',
                    'payment_method' => $method,
                    'created_by' => 'mobile_initialize',
                    'meta_ads' => $metaAds,
                ],
            ]);
        } elseif ($metaAds) {
            $meta = is_array($payment->meta) ? $payment->meta : [];
            $meta['meta_ads'] = $metaAds;
            $payment->update(['meta' => $meta]);
        }

        try {
            $checkout = $paymentService->initializePaystack(
                $order,
                $payment,
                [
                    'email' => $data['customer']['email'] ?? $order->email,
                    'name' => $data['customer']['name'] ?? $customer?->name ?? 'Customer',
                    'phone' => $data['customer']['phone'] ?? null,
                    'mobile_provider' => $data['customer']['mobile_provider'] ?? null,
                ],
                $method,
                $data['return_url'] ?? null
            );

            if (! isset($checkout['checkout_url']) && $method !== 'mobile_money') {
                return $this->error('Payment provider did not return valid response', 500);
            }

            return $this->success([
                'reference' => $checkout['reference'],
                'redirect' => $checkout['checkout_url'] ?? null,
                'checkout_url' => $checkout['checkout_url'],
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'order_number' => $order->number,
                'method' => $method,
                'status' => $checkout['status'] ?? 'pending',
                'display_text' => $checkout['display_text'] ?? null,
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
    public function redirect(Request $request, PaymentService $paymentService): JsonResponse
    {
        $reference = (string) (
            $request->input('reference')
            ?? $request->query('reference')
            ?? $request->query('trxref')
            ?? ''
        );

        if ($reference === '') {
            return $this->error('Missing payment reference', 404);
        }

        $payment = Payment::query()
            ->where('provider', 'paystack')
            ->where('provider_reference', $reference)
            ->with('order')
            ->latest('id')
            ->first();

        if (! $payment) {
            return $this->notFound('Payment not found');
        }

        $meta = is_array($payment->meta) ? $payment->meta : [];
        $meta['redirect_hit_at'] = now()->toISOString();
        $payment->update(['meta' => $meta]);

        if ($payment->status === 'paid' && $payment->order) {
            return $this->success([
                'status' => 'success',
                'payment_status' => $payment->status,
                'order_status' => $payment->order->status,
                'order_number' => $payment->order->number,
                'reference' => $payment->provider_reference,
                'redirect' => null,
            ]);
        }

        try {
            $payment = $paymentService->verifyPaystack($reference)->load('order');
        } catch (\Throwable $e) {
            Log::error('Mobile payment redirect verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Verification failed', 422);
        }

        if ($payment->status === 'paid' && $payment->order) {
            $customer = $request->user();

            if ($customer && (int) $payment->order->customer_id === (int) $customer->id) {
                $cart = Cart::query()->where('user_id', $customer->id)->first();
                $cart?->emptyCart();
            }

            return $this->success([
                'status' => 'success',
                'payment_status' => $payment->status,
                'order_status' => $payment->order->status,
                'order_number' => $payment->order->number,
                'reference' => $payment->provider_reference,
                'redirect' => null,
            ]);
        }

        return $this->success([
            'status' => 'pending',
            'payment_status' => $payment->status,
            'order_status' => $payment->order?->status,
            'order_number' => $payment->order?->number,
            'reference' => $payment->provider_reference,
            'redirect' => null,
        ]);
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
        $data = $this->validateVerifyRequest($request);
        if ($data === null) {
            return $this->error('Validation failed', 422);
        }

        $customer = $request->user();

        if (! empty($data['reference'])) {
            $result = $this->verifyByReference($data, $paymentService, $korapayService, $paymentResultService);
        } else {
            $result = $this->verifyByOrder($data, $customer);
        }

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return $this->paymentStatusResponse($result);
    }

    private function findOrderPayment(array $validated, $customer): ?Payment
    {
        $order = Order::query()
            ->where('number', (string) ($validated['order_number'] ?? ''))
            ->first();

        if (! $order || $order->customer_id !== $customer?->id) {
            return null;
        }

        return Payment::query()
            ->where('order_id', $order->id)
            ->where('provider', 'paystack')
            ->latest('id')
            ->first();
    }

    private function paymentStatusResponse(Payment $payment): JsonResponse
    {
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

    private function validateVerifyRequest(Request $request): ?array
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'nullable|string|max:255',
            'order_number' => 'nullable|string|max:64',
            'provider' => 'nullable|in:korapay,paystack,stripe,paypal',
        ]);

        if ($validator->fails()) {
            return null;
        }

        return $validator->validated();
    }

    private function verifyByReference(
        array $data,
        PaymentService $paymentService,
        KorapayService $korapayService,
        PaymentResultService $paymentResultService
    ): Payment|JsonResponse
    {
        $reference = (string) $data['reference'];
        $provider = (string) ($data['provider'] ?? 'paystack');

        $existing = Payment::query()
            ->where('provider', $provider)
            ->where('provider_reference', $reference)
            ->latest('id')
            ->first();

        if ($existing && $this->isPaidStatus($existing->status)) {
            return $existing;
        }

        try {
            return match ($provider) {
                'korapay' => $paymentService->verifyKorapay($reference),
                'paystack' => $paymentService->verifyPaystack($reference),
                default => throw new \InvalidArgumentException("Provider '{$provider}' not supported"),
            };
        } catch (\RuntimeException $exception) {
            if ($provider !== 'korapay' || !str_contains($exception->getMessage(), 'Order number missing in webhook payload')) {
                throw $exception;
            }

            return $this->korapayFallback($reference, $korapayService, $paymentResultService);
        }
    }

    private function verifyByOrder(array $data, $customer): Payment|JsonResponse
    {
        $order = Order::query()
            ->where('number', $data['order_number'] ?? '')
            ->first();

        if (! $order || $order->customer_id !== $customer?->id) {
            return $this->notFound('Order not found');
        }

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->when($data['provider'] ?? null, fn ($q, $p) => $q->where('provider', $p))
            ->latest('id')
            ->first();

        if (! $payment) {
            return $this->notFound('Payment not found');
        }

        return $payment;
    }

    private function korapayFallback(
        string $reference,
        KorapayService $korapayService,
        PaymentResultService $paymentResultService
    ): Payment|JsonResponse
    {
        $verifyResult = $korapayService->checkStatus($reference);
        $paymentStatus = strtolower((string) ($verifyResult['data']['status'] ?? ''));

        if ($paymentStatus === 'success') {
            $item = $this->getItem(request());
            if ($item) {
                try {
                    $paymentResultService->registerCompletePayment($item, $verifyResult);
                } catch (\Throwable $e) {
                    Log::warning('Mobile verify fallback registerCompletePayment failed', [
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

            return $payment;
        }

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

    private function isPaidStatus(string $status): bool
    {
        return in_array(strtolower($status), ['paid', 'success', 'succeeded', 'captured'], true);
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
