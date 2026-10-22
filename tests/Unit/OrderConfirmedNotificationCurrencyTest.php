<?php

namespace Tests\Unit;

use App\Domain\Common\Models\Address;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Notifications\Orders\OrderConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderConfirmedNotificationCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_confirmation_displays_xof_when_payment_is_xof(): void
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
            'number' => 'DS-CURRTEST',
            'email' => 'buyer@example.com',
            'status' => 'pending',
            'payment_status' => 'paid',
            'currency' => 'USD',
            'subtotal' => 72.90,
            'shipping_total' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
            'grand_total' => 72.90,
            'shipping_address_id' => $address->id,
            'billing_address_id' => $address->id,
            'placed_at' => now(),
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'korapay',
            'status' => 'paid',
            'provider_reference' => 'KPY-TEST',
            'amount' => 43740,
            'currency' => 'XOF',
            'meta' => [],
            'paid_at' => now(),
        ]);

        $notification = new OrderConfirmedNotification($order, payment: $payment);
        $mail = $notification->toMail((object) ['name' => 'Fatima']);

        $this->assertStringContainsString('XOF', implode("\n", $mail->introLines));
        $this->assertStringContainsString('43,740', implode("\n", $mail->introLines));
        $this->assertStringNotContainsString('USD', implode("\n", $mail->introLines));
    }

    public function test_order_confirmation_converts_usd_order_total_to_xof_when_payment_not_xof(): void
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
            'number' => 'DS-CURRTEST2',
            'email' => 'buyer2@example.com',
            'status' => 'pending',
            'payment_status' => 'paid',
            'currency' => 'USD',
            'subtotal' => 10.00,
            'shipping_total' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
            'grand_total' => 10.00,
            'shipping_address_id' => $address->id,
            'billing_address_id' => $address->id,
            'placed_at' => now(),
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'korapay',
            'status' => 'paid',
            'provider_reference' => 'KPY-TEST2',
            'amount' => 10.00,
            'currency' => 'USD',
            'meta' => [],
            'paid_at' => now(),
        ]);

        $notification = new OrderConfirmedNotification($order, payment: $payment);
        $mail = $notification->toMail((object) ['name' => 'Fatima']);

        $lines = implode("\n", $mail->introLines);
        $this->assertStringContainsString('XOF', $lines);
        $this->assertStringContainsString('6,000', $lines);
        $this->assertStringNotContainsString('USD', $lines);
    }
}
