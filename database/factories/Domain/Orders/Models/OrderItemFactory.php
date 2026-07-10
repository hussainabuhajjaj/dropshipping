<?php

namespace Database\Factories\Domain\Orders\Models;

use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'quantity' => $this->faker->numberBetween(1, 5),
            'unit_price' => $this->faker->randomFloat(2, 10, 200),
            'total' => function (array $attributes) {
                return $attributes['unit_price'] * $attributes['quantity'];
            },
        ];
    }
}
