<?php

namespace Tests\Unit;

use App\Domain\Common\Models\Address;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Payments\Models\Payment;
use App\Notifications\Orders\PaymentReceiptNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReceiptNotificationCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_receipt_displays_xof_only(): void
    {
        config(['currency.rates.USD_XOF' => 600]);

        $address = Address::create([
            'name' => 'Test Buyer',
            'phone' => '+22500000000',
            'line1' => '123 Test Street',
            'city' => 'Abidjan',
            'country' => 'CI',
            'type' => 'shipping',
        ]);

        $order = Order::create([
            'number' => 'DS-RECEIPT1',
            'email' => 'buyer@example.com',
            'status' => 'pending',
            'payment_status' => 'paid',
            'currency' => 'USD',
            'subtotal' => 10.00,
            'shipping_total' => 2.00,
            'tax_total' => 0.00,
            'discount_total' => 0.00,
            'grand_total' => 12.00,
            'shipping_address_id' => $address->id,
            'billing_address_id' => $address->id,
            'placed_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => null,
            'fulfillment_status' => 'pending',
            'quantity' => 1,
            'unit_price' => 10.00,
            'total' => 10.00,
            'snapshot' => ['name' => 'Test Item'],
            'meta' => [],
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'korapay',
            'status' => 'paid',
            'provider_reference' => 'KPY-RECEIPT1',
            'amount' => 12.00,
            'currency' => 'USD',
            'meta' => [],
            'method' => 'card',
            'gateway_transaction_id' => 'GTW-123',
            'paid_at' => now(),
        ]);

        $notification = new PaymentReceiptNotification($order, $payment);
        $mail = $notification->toMail((object) ['name' => 'Fatima']);

        $lines = implode("\n", $mail->introLines);
        $this->assertStringContainsString('XOF', $lines);
        $this->assertStringNotContainsString('USD', $lines);
        // 12 USD * 600 = 7,200 XOF
        $this->assertStringContainsString('7,200', $lines);
    }
}

