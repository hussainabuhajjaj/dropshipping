<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CustomerSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerSegmentFactory extends Factory
{
    protected $model = CustomerSegment::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'description' => $this->faker->optional()->sentence(),
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'locale', 'operator' => 'eq', 'value' => 'fr'],
                ],
            ],
            'is_active' => true,
        ];
    }
}
