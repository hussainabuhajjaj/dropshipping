<?php

namespace App\Services\Payments;

use App\Domain\Common\Models\Address;
use App\Events\Orders\OrderPlaced;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderShipping;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Services\AbandonedCartService;
use App\Services\Currency\CurrencyConversionService;
use App\Domain\Fulfillment\Services\FulfillmentDispatchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentResultService
{
    public function registerCompletePayment($item, $payment_result)
    {

        $order = null;
        $payment = null;

        if ($item instanceof Cart) {
            [$order, $payment] = $this->convertCartToOrder($item, $payment_result);
        }


        return $this->getRedirectAfterSuccess($order);
//        dd($result);
    }

    public function registerFailedPayment($type, $id, $verify_result = [])
    {

        if ($type === "cart") {
            // make your action here
        }

        return $this->getRedirectAfterFailed($type, $id);
    }


    public function convertCartToOrder($cart, $payment_result)
    {
        DB::beginTransaction();
        try {
            $cart_items = $cart->items;
            $customer = auth('customer')->user();
            if (! $customer) {
                throw new \RuntimeException('Authenticated customer required for checkout.');
            }
            $isGuest = false;
            if ($customer && $customer->locale !== app()->getLocale()) {
                $customer->update(['locale' => app()->getLocale()]);
            }

            $request_body = session()->get('request_body', []);
            if (!is_array($request_body)) {
                $request_body = [];
            }

            $fallbackAddress = $customer?->addresses()
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();

            $firstName = (string)($request_body['first_name'] ?? $customer?->first_name ?? $customer?->name ?? '');
            $lastName = (string)($request_body['last_name'] ?? $customer?->last_name ?? '');
            $phone = (string)($request_body['phone'] ?? $customer?->phone ?? $fallbackAddress?->phone ?? '');
            $line1 = (string)($request_body['line1'] ?? $fallbackAddress?->line1 ?? '');
            $line2 = (string)($request_body['line2'] ?? $fallbackAddress?->line2 ?? '');
            $city = (string)($request_body['city'] ?? $fallbackAddress?->city ?? '');
            $state = (string)($request_body['state'] ?? $fallbackAddress?->state ?? '');
            $postalCode = (string)($request_body['postal_code'] ?? $fallbackAddress?->postal_code ?? '');
            $country = strtoupper((string)($request_body['country'] ?? $fallbackAddress?->country ?? 'US'));
            $email = (string)($request_body['email'] ?? $customer?->email ?? '');
            if ($email === '') {
                throw new \RuntimeException('Customer email missing for order creation.');
            }

            $summery = $cart->getSummery();

            $shippingAddress = Address::query()->where('customer_id', $customer->id)
                ->find(@$request_body['address_id']);


            if (!isset($shippingAddress)) {
                $shippingAddress = Address::query()->create([
                    'user_id' => null,
                    'customer_id' => $customer->id,
                    'name' => trim($firstName . ' ' . $lastName),
                    'phone' => $phone,
                    'line1' => $line1,
                    'line2' => $line2 !== '' ? $line2 : null,
                    'city' => $city,
                    'state' => $state !== '' ? $state : null,
                    'postal_code' => $postalCode !== '' ? $postalCode : null,
                    'country' => $country,
                    'type' => 'shipping',
                ]);
            }

            $coupon = @$summery['coupon'];
            $discount_snapshot = buildDiscountSnapshot(
                @$summery['discount'],
                @$summery['discount_label'] ?? null,
                @$summery['discount_source'],
                $coupon ? $coupon->serializeCoupon() : null,
                @$summery['promotionDiscounts'],
                $cart[0]['currency'] ?? 'USD'
            );

            // Create order
            $order = Order::createWithGeneratedNumber([
                'user_id' => $customer->id,
                'customer_id' => $customer->id,
                'guest_name' => null,
                'guest_phone' => null,
                'is_guest' => false,
                'email' => $email,
                'locale' => app()->getLocale(),
                'status' => 'pending',
                'payment_status' => 'paid',
                'currency' => $cart[0]['currency'] ?? 'USD',
                'subtotal' => @$summery['subtotal'],
                'shipping_total' => @$summery['shippingTotal'],
                'shipping_total_estimated' => @$summery['shippingTotal'],
                'tax_total' => @$summery['tax_total'],
                'discount_total' => @$summery['discount'],
                'grand_total' => @$summery['total'],
                'discount_snapshot' => $discount_snapshot,
                'discount_source' => @$summery['discount_source'],
                'shipping_address_id' => $shippingAddress->id,
                'billing_address_id' => $shippingAddress->id,
                'shipping_method' => 'standard',
                'delivery_notes' => $request_body['delivery_notes'] ?? null,
                'coupon_code' => $coupon['code'] ?? null,
                'placed_at' => now(),
            ]);

            // Create order items
            $fallbackProvider = SiteSetting::query()->value('default_fulfillment_provider_id');
            $currencyConverter = app(\App\Services\Currency\CurrencyConversionService::class);
            $userCurrency = app(\App\Services\User\UserPreferenceService::class)->getPreferences()['currency'] ?? 'XOF';

            foreach ($cart_items as $line) {
                $providerId = $line['fulfillment_provider_id'] ?? $fallbackProvider;
                $supplierProduct = \App\Domain\Products\Models\SupplierProduct::query()
                    ->where('product_variant_id', $line['variant_id'])
                    ->when($providerId, fn ($query) => $query->where('fulfillment_provider_id', $providerId))
                    ->first();

                // Convert prices from USD to user's currency (XOF)
                $unitPriceInUsd = $line->getSinglePrice();
                try {
                    $unitPriceInUserCurrency = $currencyConverter->convertAmount($unitPriceInUsd, 'USD', $userCurrency);
                    if ($unitPriceInUserCurrency === null) {
                        \Log::warning('Currency conversion returned null in payment result service', [
                            'usd_price' => $unitPriceInUsd,
                            'target_currency' => $userCurrency,
                            'order_id' => $order->id,
                        ]);
                        $unitPriceInUserCurrency = $unitPriceInUsd;
                    }
                } catch (\Throwable $e) {
                    \Log::error('Currency conversion failed in payment result service', [
                        'usd_price' => $unitPriceInUsd,
                        'target_currency' => $userCurrency,
                        'error' => $e->getMessage(),
                        'order_id' => $order->id,
                    ]);
                    $unitPriceInUserCurrency = $unitPriceInUsd;
                }
                $totalInUserCurrency = $unitPriceInUserCurrency * $line['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $line['variant_id'],
                    'fulfillment_provider_id' => $providerId,
                    'supplier_product_id' => $supplierProduct?->id,
                    'fulfillment_status' => 'pending',
                    'quantity' => $line['quantity'],
                    'unit_price' => $unitPriceInUserCurrency,
                    'total' => $totalInUserCurrency,
                    'source_sku' => $supplierProduct?->external_sku ?? $line->variant?->sku,
                    'snapshot' => [
                        'name' => @$line?->product['name'],
                        'variant' => @$line?->variant['title'],
                        'supplier_type' => $line->product?->supplier_type,
                    ],
                    'meta' => [
                        'media' => $line['media'] ?? null,
                        'coupon_code' => $coupon['code'] ?? null,
                        'supplier_type' => $line->product?->supplier_type,
                        'supplier_product_id' => $supplierProduct?->id,
                        'external_product_id' => $supplierProduct?->external_product_id,
                        'external_sku' => $supplierProduct?->external_sku,
                    ],
                ]);
            }

            app(\App\Domain\Orders\Services\OrderCostBreakdownService::class)->recalculate($order);

            foreach ($cart->shippings as $shipping) {
                $shipping = $shipping->toArray();
                $shipping['order_id'] = $order->id;
                $shipping['name'] = $shipping['logistic_name'];
                $shipping['price'] = $shipping['logistic_price'];
                OrderShipping::query()->create($shipping);
            }

            $order->recordPromotionUsage(@$summery['promotionDiscounts'], @$summery['subtotal'], @$summery['discount_source']);
            if (isset($coupon)) {
                $coupon->redeemCoupon($customer, $order, @$summery['discount_source'], @$summery['discount']);
            }

            $paymentPayload = $this->resolvePaymentPayload($payment_result, (float) ($order->grand_total ?? 0), (string) ($order->currency ?? 'USD'));

            // Create payment
            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'provider' => 'korapay',
                'status' => $paymentPayload['status'],
                'provider_reference' => @$payment_result['data']['reference'],
                'amount' => $paymentPayload['amount'],
                'currency' => $paymentPayload['currency'],
                'paid_at' => now(),
                'meta' => $payment_result['data'],
            ]);

            event(new OrderPlaced($order));
            app(FulfillmentDispatchService::class)->dispatchForOrder($order);

            $cart->emptyCart();
            app(AbandonedCartService::class)->markRecovered();


            DB::commit();
            return [$order, $payment];

        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
//        return DB::transaction(function () use ($cart, $payment_result) {
//            $cart_items = $cart->items;
//            $customer = auth('customer')->user();
//            $isGuest = !$customer;
//            if ($customer && $customer->locale !== app()->getLocale()) {
//                $customer->update(['locale' => app()->getLocale()]);
//            }
//
//            $request_body = session()->get('request_body');
//
//            $summery = $cart->getSummery();
//
//            $shippingAddress = Address::query()->where('customer_id', auth('customer')->id())
//                ->find(@$request_body['address_id']);
//
//
//            if (!isset($shippingAddress)) {
//                $shippingAddress = Address::query()->create([
//                    'user_id' => null,
//                    'customer_id' => $customer?->id,
//                    'name' => trim($request_body['first_name'] . ' ' . ($request_body['last_name'] ?? '')),
//                    'phone' => $request_body['phone'],
//                    'line1' => $request_body['line1'],
//                    'line2' => $request_body['line2'] ?? null,
//                    'city' => $request_body['city'],
//                    'state' => $request_body['state'] ?? null,
//                    'postal_code' => $request_body['postal_code'] ?? null,
//                    'country' => strtoupper($request_body['country']),
//                    'type' => 'shipping',
//                ]);
//            }
//
//            $coupon = @$summery['coupon'];
//            $discount_snapshot = buildDiscountSnapshot(
//                @$summery['discount'],
//                @$summery['discount_label'] ?? null,
//                @$summery['discount_source'],
//                $coupon ? $coupon->serializeCoupon() : null,
//                @$summery['promotionDiscounts'],
//                $cart[0]['currency'] ?? 'USD'
//            );
//
//            // Create order
//            $order = Order::query()->create([
//                'number' => Order::generateOrderNumber(),
//                'user_id' => null,
//                'customer_id' => $customer?->id,
//                'guest_name' => $isGuest ? trim($request_body['first_name'] . ' ' . ($request_body['last_name'] ?? '')) : null,
//                'guest_phone' => $isGuest ? $request_body['phone'] : null,
//                'is_guest' => $isGuest,
//                'email' => $request_body['email'],
//                'locale' => app()->getLocale(),
//                'status' => 'pending',
//                'payment_status' => 'success',
//                'currency' => $cart[0]['currency'] ?? 'USD',
//                'subtotal' => @$summery['subtotal'],
//                'shipping_total' => @$summery['shippingTotal'],
//                'shipping_total_estimated' => @$summery['shippingTotal'],
//                'tax_total' => @$summery['tax_total'],
//                'discount_total' => @$summery['discount'],
//                'grand_total' => @$summery['total'],
//                'discount_snapshot' => $discount_snapshot,
//                'discount_source' => @$summery['discount_source'],
//                'shipping_address_id' => $shippingAddress->id,
//                'billing_address_id' => $shippingAddress->id,
//                'shipping_method' => 'standard',
//                'delivery_notes' => $request_body['delivery_notes'] ?? null,
//                'coupon_code' => $coupon['code'] ?? null,
//                'placed_at' => now(),
//            ]);
//
//            // Create order items
//            $fallbackProvider = SiteSetting::query()->value('default_fulfillment_provider_id');
//
//            foreach ($cart_items as $line) {
//                OrderItem::create([
//                    'order_id' => $order->id,
//                    'product_variant_id' => $line['variant_id'],
//                    'fulfillment_provider_id' => $line['fulfillment_provider_id'] ?? $fallbackProvider,
//                    'supplier_product_id' => null,
//                    'fulfillment_status' => 'pending',
//                    'quantity' => $line['quantity'],
//                    'unit_price' => $line->getSinglePrice(),
//                    'total' => $line->getSinglePrice() * $line['quantity'],
//                    'source_sku' => null,
//                    'snapshot' => [
//                        'name' => @$line?->product['name'],
//                        'variant' => @$line?->variant['title'],
//                    ],
//                    'meta' => [
//                        'media' => $line['media'] ?? null,
//                        'coupon_code' => $coupon['code'] ?? null,
//                    ],
//                ]);
//            }
//
//            app(\App\Domain\Orders\Services\OrderCostBreakdownService::class)->recalculate($order);

//            foreach ($cart->shippings as $shipping) {
//                $shipping = $shipping->toArray();
//                $shipping['order_id'] = $order->id;
//                $shipping['name'] = $shipping['logistic_name'];
//                $shipping['price'] = $shipping['logistic_price'];
//                OrderShipping::query()->create($shipping);
//            }
//
//            $order->recordPromotionUsage(@$summery['promotionDiscounts'], @$summery['subtotal'], @$summery['discount_source']);
//            if (isset($coupon)) {
//                $coupon->redeemCoupon($customer, $order, @$summery['discount_source'], @$summery['discount']);
//            }
//
//            // Create payment
//            $payment = Payment::query()->create([
//                'order_id' => $order->id,
//                'provider' => 'korapay',
//                'status' => @$payment_result['data']['status'],
//                'provider_reference' => @$payment_result['data']['reference'],
//                'amount' => @$payment_result['data']['amount_paid'],
//                'currency' => $payment_result['data']['currency'],
//                'paid_at' => now(),
//                'meta' => $payment_result['data'],
//            ]);
//
//            event(new OrderPlaced($order));
//
//            $cart->emptyCart();
//            app(AbandonedCartService::class)->markRecovered();
//
//            return [$order, $payment];
//        });
    }

    private function resolvePaymentPayload(array $paymentResult, float $orderGrandTotal, string $orderCurrency): array
    {
        $statusRaw = strtolower((string) data_get($paymentResult, 'data.status', 'pending'));
        $normalizedStatus = in_array($statusRaw, ['success', 'succeeded', 'captured', 'paid'], true)
            ? 'paid'
            : ($statusRaw ?: 'pending');

        $providerCurrency = strtoupper((string) (data_get($paymentResult, 'data.currency') ?: $orderCurrency ?: 'USD'));
        $rawAmount = data_get($paymentResult, 'data.amount_paid', data_get($paymentResult, 'data.amount'));
        $providerAmount = is_numeric($rawAmount) ? (float) $rawAmount : null;

        // Fallback to order total when provider amount is missing/invalid.
        if ($providerAmount === null || $providerAmount <= 0) {
            $providerAmount = $this->convertFromOrderCurrency($orderGrandTotal, $orderCurrency, $providerCurrency);
        }

        // If provider currency differs and payload amount is suspiciously far from converted total, correct it.
        if ($providerCurrency !== strtoupper($orderCurrency)) {
            $expected = $this->convertFromOrderCurrency($orderGrandTotal, $orderCurrency, $providerCurrency);
            if ($expected > 0 && abs($providerAmount - $expected) > max(5.0, $expected * 0.05)) {
                Log::warning('Provider amount deviates from converted order total, using converted fallback', [
                    'provider_amount' => $providerAmount,
                    'expected' => $expected,
                    'provider_currency' => $providerCurrency,
                    'order_total' => $orderGrandTotal,
                    'order_currency' => $orderCurrency,
                ]);
                $providerAmount = $expected;
            }
        }

        return [
            'status' => $normalizedStatus,
            'amount' => $providerAmount,
            'currency' => $providerCurrency,
        ];
    }

    private function convertFromOrderCurrency(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if (strtoupper($fromCurrency) === strtoupper($toCurrency)) {
            return round($amount, 2);
        }

        try {
            return (float) app(CurrencyConversionService::class)->convertAmount($amount, strtoupper($fromCurrency), strtoupper($toCurrency));
        } catch (\Throwable $e) {
            Log::warning('Currency conversion fallback failed, using order total as-is', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return round($amount, 2);
        }
    }

    public function getRedirectAfterSuccess($item)
    {
        if ($item instanceof Order) {
            Log::info('entered to route ' . $item->number);
            return route('orders.confirmation', ['number' => $item->number]);
        }
    }

    public function getRedirectAfterFailed($type, $id)
    {
        if ($type === "cart") {
            return route('pay.index', [$type, $id]);
        } elseif ($type === "gift") {
            //
        }
    }
}
