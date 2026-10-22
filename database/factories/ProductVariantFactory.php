<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'title' => $this->faker->word(),
            'sku' => $this->faker->unique()->bothify('SKU-#####'),
            'price' => $this->faker->numberBetween(500, 10000),
        ];
    }
}
