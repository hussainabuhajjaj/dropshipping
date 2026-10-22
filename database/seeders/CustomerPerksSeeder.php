<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\GiftCard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomerPerksSeeder extends Seeder
{
    public function run(): void
    {
        Coupon::firstOrCreate(
            ['code' => 'WELCOME10'],
            [
                'description' => '10% off your first order',
                'type' => 'percent',
                'amount' => 10,
                'is_active' => true,
            ]
        );

        Coupon::firstOrCreate(
            ['code' => 'SHIP5'],
            [
                'description' => '$5 off shipping',
                'type' => 'fixed',
                'amount' => 5,
                'is_active' => true,
            ]
        );

        Coupon::firstOrCreate(
            ['code' => 'Simbazu20'],
            [
                'description' => '20% off select categories',
                'type' => 'percent',
                'amount' => 20,
                'is_active' => true,
            ]
        );

        if (! GiftCard::query()->exists()) {
            for ($i = 0; $i < 3; $i++) {
                GiftCard::create([
                    'code' => 'GIFT-' . Str::upper(Str::random(8)),
                    'balance' => 25 * ($i + 1),
                    'currency' => 'USD',
                    'status' => 'active',
                ]);
            }
        }
    }
}
