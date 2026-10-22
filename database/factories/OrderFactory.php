<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Customer;
use App\Domain\Common\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $customer = Customer::factory()->create();
        $address = Address::factory()->create(['customer_id' => $customer->id]);
        
        $subtotal = $this->faker->randomFloat(2, 20, 500);
        $tax = round($subtotal * 0.1, 2);
        $shipping = $this->faker->randomFloat(2, 5, 20);
        $total = $subtotal + $tax + $shipping;
        
        return [
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'number' => $this->faker->unique()->numerify('ORD-########'),
            'status' => $this->faker->randomElement(['pending', 'processing', 'shipped', 'delivered', 'cancelled']),
            'payment_status' => $this->faker->randomElement(['pending', 'paid', 'refunded', 'failed']),
            'currency' => 'USD',
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'shipping_total' => $shipping,
            'grand_total' => $total,
            'shipping_address_id' => $address->id,
            'billing_address_id' => $address->id,
            'is_guest' => false,
        ];
    }
}
