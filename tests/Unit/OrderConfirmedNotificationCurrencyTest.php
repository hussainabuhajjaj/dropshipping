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

    public function test_order_confirmation_prefers_payment_currency_when_available(): void
    {
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
    }
}

