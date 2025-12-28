<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'selling_price' => $this->faker->numberBetween(1000, 100000),
            'cost_price' => $this->faker->numberBetween(500, 50000),
            'slug' => $this->faker->unique()->slug(),
            'status' => 'active',
        ];
    }
}
