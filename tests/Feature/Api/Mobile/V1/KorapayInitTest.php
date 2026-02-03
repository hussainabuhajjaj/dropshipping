<?php

namespace Tests\Feature\Api\Mobile\V1;

use App\Domain\Common\Models\Address;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KorapayInitTest extends TestCase
{
    use RefreshDatabase;

    public function test_korapay_init_returns_reference_and_url(): void
    {
        config([
            'services.korapay.base_url' => 'https://korapay.test',
            'services.korapay.secret_key' => 'secret',
            'services.korapay.initialize_endpoint' => '/init',
            'services.korapay.verify_endpoint' => '/verify/{reference}',
        ]);

        Http::fake([
            'https://korapay.test/init' => Http::response([
                'status' => true,
                'data' => [
                    'reference' => 'ref_123',
                    'checkout_url' => 'https://pay.test/checkout',
                ],
            ], 200),
        ]);

        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $address = Address::create([
            'name' => 'Test Buyer',
            'phone' => '+22500000000',
            'line1' => '123 Test Street',
            'city' => 'Abidjan',
            'country' => 'CI',
            'type' => 'shipping',
        ]);

        $order = Order::create([
            'number' => 'DS-0000000001',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 99.99,
            'shipping_total' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
            'grand_total' => 99.99,
            'shipping_address_id' => $address->id,
            'billing_address_id' => $address->id,
            'placed_at' => now(),
        ]);

        $response = $this->postJson('/api/mobile/v1/payments/korapay/init', [
            'order_number' => $order->number,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reference', 'ref_123')
            ->assertJsonPath('data.checkout_url', 'https://pay.test/checkout');
    }
}
