<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('COUPON-??????')),
            'type' => $this->faker->randomElement(['percent', 'fixed']),
            'amount' => $this->faker->randomFloat(2, 5, 50),
            'is_active' => true,
            'max_uses' => 0,
            'uses' => 0,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'affects_affiliate_commission' => true,
        ];
    }
}
