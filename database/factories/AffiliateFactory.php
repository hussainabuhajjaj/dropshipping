<?php

namespace Database\Factories;

use App\Domain\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AffiliateFactory extends Factory
{
    protected $model = Affiliate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'referral_code' => strtoupper($this->faker->lexify('REF??????')),
            'commission_rate' => 0.10,
            'status' => 'approved',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => 'suspended',
        ]);
    }
}
