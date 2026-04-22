<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Common\Models\Address;
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
use Illuminate\Validation\Rule;

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
        $customer = auth('customer')->user();

        $validator = Validator::make(
            $request->all(),
            array_merge([
                'payment_method' => ['required', 'string', 'in:card,mobile_money'],
                'email' => ['required', 'email'],
                'grand_total' => ['required', 'numeric', 'min:1'],
                'phone' => [filled(request('address_id')) ? 'nullable' : 'required', 'string', 'max:30'],
                'mobile_provider' => ['nullable', 'string', 'max:50'],
            ], $this->addressValidationRules($customer?->id)),
            [
                'address_id.required' => 'Please select a shipping address before continuing.',
                'line1.required' => 'Shipping address line 1 is required.',
                'city.required' => 'Shipping city is required.',
                'country.required' => 'Shipping country is required.',
                'phone.required' => 'Shipping phone number is required.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => $validator->errors()->first() ?: 'A valid shipping address is required before payment.',
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

            $address = $this->resolveShippingAddress($request, $customer?->id);

            // Create order with backend-validated amount
            $order = Order::create([
                'number' => 'ORD-' . uniqid(),
                'customer_id' => $customer?->id,
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

    private function addressValidationRules(?int $customerId): array
    {
        if (filled(request('address_id'))) {
            return [
                'address_id' => [
                    'required',
                    'integer',
                    Rule::exists('addresses', 'id')->where(fn ($query) => $query->where('customer_id', $customerId)),
                ],
                'delivery_notes' => ['nullable', 'string', 'max:500'],
            ];
        }

        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['required', 'string', 'size:2'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function resolveShippingAddress(Request $request, ?int $customerId): Address
    {
        $addressId = $request->integer('address_id');

        if ($addressId) {
            $address = Address::query()
                ->where('customer_id', $customerId)
                ->findOrFail($addressId);
            
            // If phone is provided in request, update the existing address
            if ($request->filled('phone') && $request->input('phone') !== $address->phone) {
                $address->update(['phone' => $request->input('phone')]);
            }
            
            return $address;
        }

        return Address::query()->create([
            'customer_id' => $customerId,
            'name' => trim(implode(' ', array_filter([
                (string) $request->input('first_name', ''),
                (string) $request->input('last_name', ''),
            ]))),
            'phone' => (string) $request->input('phone', ''),
            'line1' => (string) $request->input('line1', ''),
            'line2' => filled($request->input('line2')) ? (string) $request->input('line2') : null,
            'city' => (string) $request->input('city', ''),
            'state' => filled($request->input('state')) ? (string) $request->input('state') : null,
            'postal_code' => filled($request->input('postal_code')) ? (string) $request->input('postal_code') : null,
            'country' => strtoupper((string) $request->input('country', '')),
            'type' => 'shipping',
        ]);
    }


    
    private function clearCustomerCart(Payment $payment): void
    {
        $order = $payment->order;
        $customerId = $order?->customer_id;

        if (! $customerId) {
            return;
        }

        $cart = \App\Models\Cart::query()->where('user_id', $customerId)->first();
        if (! $cart) {
            return;
        }

        Log::info('Clearing customer cart after successful Paystack payment', [
            'reference' => $payment->provider_reference,
            'payment_id' => $payment->id,
            'order_id' => $order?->id,
            'customer_id' => $customerId,
            'cart_id' => $cart->id,
        ]);

        $cart->emptyCart();
        app(\App\Services\AbandonedCartService::class)->markRecovered();
    }
}
