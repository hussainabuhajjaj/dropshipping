<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LocalWareHouse;
use Illuminate\Database\Seeder;

class LocalWareHouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            [
                'id' => 1,
                'name' => 'Main China Warehouse',
                'phone' => '+86 21 5555 0000',
                'line1' => 'No. 1 Logistics Rd',
                'line2' => 'Pudong District',
                'city' => 'Shanghai',
                'state' => 'Shanghai',
                'postal_code' => '200120',
                'country' => 'CN',
                'is_default' => true,
            ],
            [
                'id' => 2,
                'name' => 'US Receiving Warehouse',
                'phone' => '+1 909 555 0100',
                'line1' => '4100 Warehouse Ave',
                'line2' => null,
                'city' => 'Ontario',
                'state' => 'CA',
                'postal_code' => '91761',
                'country' => 'US',
                'is_default' => false,
            ],
        ];

        foreach ($warehouses as $warehouse) {
            $id = $warehouse['id'];
            unset($warehouse['id']);

            LocalWareHouse::updateOrCreate(
                ['id' => $id],
                $warehouse
            );
        }
    }
}
