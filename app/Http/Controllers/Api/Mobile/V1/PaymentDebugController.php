<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Api\Mobile\V1\ApiController;
use App\Domain\Payments\PaymentService;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentDebugController extends ApiController
{
    /**
     * Test payment initialization and redirect URL
     */
    public function testRedirectUrl(Request $request, PaymentService $paymentService): JsonResponse
    {
        $orderNumber = $request->input('order_number', 'DS-TEST123456');
        
        // Find or create test order
        $order = Order::where('number', $orderNumber)->first();
        if (!$order) {
            return $this->error('Test order not found', 404);
        }

        // Find payment
        $payment = Payment::where('order_id', $order->id)
            ->where('provider', 'korapay')
            ->latest()
            ->first();

        if (!$payment) {
            return $this->error('Test payment not found', 404);
        }

        // Get current redirect URL configuration
        $redirectUrl = url('/api/mobile/v1/payments/redirect');
        
        // Test what would be sent to Korapay
        $testPayload = [
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'reference' => $payment->provider_reference,
            'redirect_url' => $redirectUrl,
            'customer' => [
                'email' => $order->email,
                'name' => $order->guest_name ?? $order->customer?->name,
            ],
            'channels' => ['mobile_money'],
            'default_channel' => 'mobile_money',
            'metadata' => [
                'order_number' => $order->number,
                'payment_id' => $payment->id,
                'customer_id' => $order->customer_id,
                'payment_method' => 'mobile_money',
            ],
        ];

        return $this->success([
            'order_number' => $order->number,
            'payment_id' => $payment->id,
            'provider_reference' => $payment->provider_reference,
            'redirect_url_configured' => $redirectUrl,
            'korapay_payload' => $testPayload,
            'current_meta' => $payment->meta,
            'app_url' => config('app.url'),
            'base_url' => url('/'),
        ]);
    }

    /**
     * Simulate redirect request to test data capture
     */
    public function simulateRedirect(Request $request): JsonResponse
    {
        $reference = $request->input('reference');
        if (!$reference) {
            return $this->error('Reference required', 400);
        }

        $payment = Payment::where('provider_reference', $reference)->first();
        if (!$payment) {
            return $this->error('Payment not found', 404);
        }

        // Simulate redirect data capture
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $meta['redirect_hit_at'] = now()->toISOString();
        $meta['redirect_payload'] = [
            'query' => $request->query(),
            'input' => $request->all(),
            'path' => $request->path(),
            'headers' => $request->headers->all(),
        ];
        
        $payment->forceFill(['meta' => $meta])->save();

        return $this->success([
            'message' => 'Redirect data captured',
            'payment_id' => $payment->id,
            'redirect_data' => $meta['redirect_payload'],
        ]);
    }

    /**
     * Get complete payment data for debugging
     */
    public function getPaymentData(Request $request): JsonResponse
    {
        $orderNumber = $request->input('order_number');
        $reference = $request->input('reference');

        if ($orderNumber) {
            $order = Order::where('number', $orderNumber)
                ->with(['payments', 'payments.webhooks', 'payments.events'])
                ->first();

            if (!$order) {
                return $this->error('Order not found', 404);
            }

            return $this->success([
                'order' => $order->toArray(),
                'payments' => $order->payments->toArray(),
                'webhooks' => $order->payments->flatMap->webhooks->toArray(),
                'events' => $order->payments->flatMap->events->toArray(),
            ]);
        }

        if ($reference) {
            $payment = Payment::where('provider_reference', $reference)
                ->with(['order', 'webhooks', 'events'])
                ->first();

            if (!$payment) {
                return $this->error('Payment not found', 404);
            }

            return $this->success([
                'payment' => $payment->toArray(),
                'webhooks' => $payment->webhooks->toArray(),
                'events' => $payment->events->toArray(),
            ]);
        }

        return $this->error('Order number or reference required', 400);
    }
}
