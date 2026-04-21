<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Payments\PaymentService;
use App\Infrastructure\Payments\Paystack\PaystackService;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaystackController extends Controller
{
    public function __construct(
        private readonly PaystackService $paystackService,
        private readonly PaymentService $paymentService,
        private readonly CurrencyConversionService $currencyConversionService,
    ) {}

    /**
     * STEP 1: Initialize payment
     */
    public function initialize(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => ['required', 'string', 'in:card,mobile_money'],
            'email' => ['required', 'email'],
            'grand_total' => ['required', 'numeric', 'min:1'], // Required but will be validated
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile_provider' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Debug: Log all incoming data
            Log::info('Paystack initialize request data', [
                'all_request_data' => $request->all(),
                'frontend_grand_total' => $request->grand_total,
                'payment_method' => $request->payment_method,
            ]);

            // Validate and normalize the frontend amount
            // Frontend should send summary.raw.total (could be in any currency)
            $frontendAmount = (float) $request->grand_total;
            $frontendCurrency = $request->input('currency', 'XOF'); // Get currency from request or default to XOF

            // Security: Ensure amount is reasonable and positive
            if ($frontendAmount <= 0 || $frontendAmount > 1000000) {
                Log::warning('Invalid amount from frontend', [
                    'frontend_amount' => $frontendAmount,
                    'frontend_currency' => $frontendCurrency,
                    'email' => $request->email,
                ]);
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid amount provided',
                ], 422);
            }

            // Convert to XOF if needed using CurrencyConversionService
            $finalAmount = $frontendAmount;
            if ($frontendCurrency !== 'XOF') {
                $finalAmount = $this->currencyConversionService->convertAmount($frontendAmount, $frontendCurrency, 'XOF');
                Log::info('Currency conversion applied for Paystack', [
                    'from_currency' => $frontendCurrency,
                    'to_currency' => 'XOF',
                    'original_amount' => $frontendAmount,
                    'converted_amount' => $finalAmount,
                ]);
            }

            // Backend enforces the final XOF amount
            $amount = (int) round($finalAmount);

            // Debug: Log final amount computation
            $normalizedForPaystack = $this->paystackService->normalizeAmount($amount);
            Log::info('Paystack amount validation', [
                'frontend_grand_total' => $frontendAmount,
                'frontend_currency' => $frontendCurrency,
                'final_xof_amount' => $amount,
                'currency' => 'XOF',
                'normalized_for_paystack' => $normalizedForPaystack,
                'paystack_will_see' => $normalizedForPaystack / 100, // What Paystack will display
            ]);

            // Create shipping address first
            $address = \App\Domain\Common\Models\Address::create([
                'customer_id' => auth()->id(), // TODO: Get from auth when available
                'name' => $request->email ?? 'Customer',
                'line1' => 'Address not provided',
                'city' => 'City not provided',
                'country' => 'CI',
                'type' => 'shipping',
            ]);

            // Create order with backend-validated amount
            $order = Order::create([
                'number' => 'ORD-' . uniqid(),
                'email' => $request->email,
                'status' => 'pending',
                'payment_status' => 'pending',
                'currency' => 'XOF',
                'grand_total' => $amount, // Backend-validated amount from summary.raw.total
                'shipping_address_id' => $address->id,
                'billing_address_id' => $address->id,
            ]);

            // Create payment with backend-validated amount
            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => 'paystack',
                'status' => 'pending',
                'provider_reference' => 'pstk_' . uniqid(),
                'amount' => $amount, // Backend-validated amount from summary.raw.total
                'currency' => 'XOF',
            ]);

            $result = $this->paystackService->initializeTransaction(
                $order,
                $payment,
                $request->email,
                $request->email ?? 'Customer'
            );

            return response()->json([
                'status' => true,
                'data' => [
                    'authorization_url' => $result['authorization_url'],
                    'reference' => $result['reference'],
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('Paystack init failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * STEP 2: Paystack callback
     */
    public function callback(Request $request): RedirectResponse
    {
        $reference = $request->query('reference');

        if (!$reference) {
            return redirect('/')->with('error', 'Missing reference');
        }

        try {
            $data = $this->paystackService->verify($reference);

            $payment = Payment::where('provider_reference', $reference)->firstOrFail();

            if ($data['status'] === 'success') {
                $payment->update(['status' => 'paid']);

                $payment->order->update([
                    'payment_status' => 'paid',
                    'status' => 'processing',
                ]);

                return redirect('/success');
            }

            return redirect('/failed');

        } catch (\Throwable $e) {
            Log::error('Paystack verify failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect('/failed');
        }
    }
}
