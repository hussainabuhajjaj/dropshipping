<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'tracking_number' => $this->faker->unique()->numerify('###############'),
            'carrier' => $this->faker->randomElement(['DHL', 'FedEx', 'UPS']),
            'shipped_at' => $this->faker->dateTimeThisMonth(),
            'delivered_at' => null,
        ];
    }
}
