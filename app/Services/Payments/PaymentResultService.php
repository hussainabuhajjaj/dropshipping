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
use Illuminate\Support\Facades\DB;

class PaymentResultService
{
    public function registerCompletePayment($item, $payment_result)
    {

        if ($item instanceof Cart) {
            [$order, $payment] = $this->convertCartToOrder($item, $payment_result);
        }

        return $this->getRedirectAfterSuccess($order);
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
        return DB::transaction(function () use ($cart, $payment_result) {
            $cart_items = $cart->items;
            $customer = auth('customer')->user();
            $isGuest = !$customer;
            if ($customer && $customer->locale !== app()->getLocale()) {
                $customer->update(['locale' => app()->getLocale()]);
            }

            $request_body = session()->get('request_body');

            $summery = $cart->getSummery();

            if (isset($request_body['address_id'])) {
                // fetch from addresses
                $shippingAddress = Address::query()->where('customer_id', $request_body['address_id'])->first();
            } else {
                $shippingAddress = Address::query()->create([
                    'user_id' => null,
                    'customer_id' => $customer?->id,
                    'name' => trim($request_body['first_name'] . ' ' . ($request_body['last_name'] ?? '')),
                    'phone' => $request_body['phone'],
                    'line1' => $request_body['line1'],
                    'line2' => $request_body['line2'] ?? null,
                    'city' => $request_body['city'],
                    'state' => $request_body['state'] ?? null,
                    'postal_code' => $request_body['postal_code'] ?? null,
                    'country' => strtoupper($request_body['country']),
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
            $order = Order::query()->create([
                'number' => Order::generateOrderNumber(),
                'user_id' => null,
                'customer_id' => $customer?->id,
                'guest_name' => $isGuest ? trim($request_body['first_name'] . ' ' . ($request_body['last_name'] ?? '')) : null,
                'guest_phone' => $isGuest ? $request_body['phone'] : null,
                'is_guest' => $isGuest,
                'email' => $request_body['email'],
                'locale' => app()->getLocale(),
                'status' => 'pending',
                'payment_status' => 'success',
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

            foreach ($cart_items as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $line['variant_id'],
                    'fulfillment_provider_id' => $line['fulfillment_provider_id'] ?? $fallbackProvider,
                    'supplier_product_id' => null,
                    'fulfillment_status' => 'pending',
                    'quantity' => $line['quantity'],
                    'unit_price' => $line->getSinglePrice(),
                    'total' => $line->getSinglePrice() * $line['quantity'],
                    'source_sku' => null,
                    'snapshot' => [
                        'name' => @$line?->product['name'],
                        'variant' => @$line?->variant['title'],
                    ],
                    'meta' => [
                        'media' => $line['media'] ?? null,
                        'coupon_code' => $coupon['code'] ?? null,
                    ],
                ]);
            }

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

            // Create payment
            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'provider' => 'korapay',
                'status' => @$payment_result['data']['status'],
                'provider_reference' => @$payment_result['data']['reference'],
                'amount' => @$payment_result['data']['amount_paid'],
                'currency' => $payment_result['data']['currency'],
                'paid_at' => now(),
                'meta' => $payment_result['data'],
            ]);

            event(new OrderPlaced($order));

            $cart->emptyCart();
            app(AbandonedCartService::class)->markRecovered();

            return [$order, $payment];
        });
    }

    public function getRedirectAfterSuccess($item)
    {
        if ($item instanceof Order) {
            return redirect()->route('orders.confirmation', ['number' => $item->number]);
        }
    }

    public function getRedirectAfterFailed($type, $id)
    {
        if ($type === "cart") {
            return redirect()->route('pay.index', [$type, $id]);
        }elseif ($type === "gift"){
            //
        }
    }
}
