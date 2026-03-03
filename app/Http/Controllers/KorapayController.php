<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class KorapayController extends Controller
{
    /**
     * Initialize Korapay payment
     */
    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'order_id' => 'required|string',
            'metadata' => 'nullable|array'
        ]);

        // Generate unique reference
        $reference = 'KPY-' . Str::upper(Str::random(12)) . '-' . time();

        $customer = auth('customer')->user();
        return response()->json([
            'success' => true,
            'data' => [
                'reference' => $reference,
                'public_key' => config('services.korapay.public_key'), // Get from config
                'amount' => $validated['amount'],
                'currency' => "USD",
                'customer' => [
                    'name' => $customer->first_name,
                    'email' => $customer->email,
                ],
                'notification_url' => route('korapay.webhook'),
                'metadata' => array_merge($validated['metadata'] ?? [], [
                    'order_id' => $validated['order_id']
                ])
            ]
        ]);
    }

    /**
     * Handle Korapay webhook
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();

        // Verify webhook signature (optional but recommended)
        $signature = $request->header('x-korapay-signature');
        // Verify signature using your secret key

        if ($payload['event'] === 'charge.success') {
            $data = $payload['data'];

            // Update transaction status
            $reference = $data['payment_reference'];
            $status = $data['status'];
            $amount = $data['amount'];
            $transactionStatus = $data['transaction_status'];

            // Find and update order/transaction
            // $order = Order::where('reference', $reference)->first();
            // if ($order) {
            //     $order->update(['payment_status' => 'paid']);
            //     // Additional logic here
            // }

            // You can also access metadata
            $metadata = $data['metadata'] ?? [];
            $orderId = $metadata['order_id'] ?? null;

            \Log::info('Korapay webhook received', $payload);
        }

        return response()->json(['status' => 'success']);
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
