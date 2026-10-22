<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'company' => $this->faker->company(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'country' => $this->faker->country(),
            'website' => $this->faker->optional()->url(),
            'rating' => $this->faker->randomFloat(1, 0, 5),
            'lead_time_days' => $this->faker->numberBetween(7, 60),
            'minimum_order_qty' => $this->faker->randomElement([50, 100, 200, 500, 1000]),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }
}
