<?php

namespace App\Http\Controllers;

use App\Domain\Payments\PaymentService;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class KorapayController extends Controller
{
    /**
     * Initialize Korapay payment
     */
    public function initialize(Request $request, PaymentService $paymentService)
    {
        $validated = $request->validate([
            // Prefer order_number; order_id is kept for backward compatibility.
            'order_number' => 'nullable|string|max:64',
            'order_id' => 'nullable|integer',
            'method' => 'nullable|in:card,mobile_money',
            'metadata' => 'nullable|array',
        ]);

        $order = null;
        if (! empty($validated['order_number'] ?? null)) {
            $order = Order::query()->where('number', (string) $validated['order_number'])->first();
        } elseif (! empty($validated['order_id'] ?? null)) {
            $order = Order::query()->find((int) $validated['order_id']);
        }

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        $customer = auth('customer')->user();

        // Create a real Payment record so webhook/verify/redirect can always reconcile.
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'korapay',
            'status' => 'pending',
            'provider_reference' => null,
            'amount' => (float) ($order->grand_total ?? 0),
            'currency' => (string) ($order->currency ?? 'USD'),
            'meta' => [
                'source' => 'korapay_controller_initialize',
                'request' => $validated,
                'metadata' => $validated['metadata'] ?? null,
            ],
        ]);

        $method = (string) ($validated['method'] ?? 'card');
        $returnUrl = route('pay.redirect.with-id', ['type' => 'order', 'id' => $order->id]);
        $checkout = $paymentService->initializeKorapay(
            order: $order,
            payment: $payment,
            customer: [
                'name' => $customer?->name ?? trim(($customer?->first_name ?? '') . ' ' . ($customer?->last_name ?? '')) ?: 'Customer',
                'email' => $customer?->email ?? (string) ($order->email ?? 'no-reply@simbazu.com'),
            ],
            method: $method,
            returnUrl: $returnUrl,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'reference' => $checkout['reference'] ?? $payment->provider_reference,
                'public_key' => config('services.korapay.public_key'),
                'checkout_url' => $checkout['checkout_url'] ?? null,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'customer' => [
                    'name' => $customer?->first_name ?? $customer?->name,
                    'email' => $customer?->email,
                ],
                // Korapay webhook should be configured in Korapay dashboard.
                // This is returned for visibility only.
                'webhook_url' => url('/api/webhooks/korapay'),
                'redirect_url' => $returnUrl,
                'metadata' => array_merge($validated['metadata'] ?? [], [
                    'order_number' => $order->number,
                    'payment_id' => $payment->id,
                ]),
            ]
        ]);
    }

    /**
     * Handle Korapay webhook
     */
    public function webhook(Request $request)
    {
        // Legacy endpoint: keep returning 200 so Korapay doesn't retry, but do not process payments here.
        // The supported webhook is `POST /api/webhooks/korapay`.
        return response()->json([
            'success' => true,
            'ignored' => true,
            'message' => 'Use /api/webhooks/korapay for Korapay webhook processing.',
        ]);
    }

    /**
     * Verify transaction (optional - can be called from frontend)
     */
    public function verify(Request $request)
    {
        $reference = $request->reference;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.korapay.secret_key')
        ])->get("https://api.korapay.com/v1/charges/{$reference}");

        return response()->json($response->json());
    }
}
