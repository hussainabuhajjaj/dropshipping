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
                'name' => 'Guangzhou Yuexiu Warehouse',
                'phone' => '+86 20 0000 0000',
                'line1' => '广州市越秀区环市西路202号富立国际大厦5楼514',
                'line2' => 'Room 514, 5th Floor, Fuli International Building, No. 202 Huan shi West Road, Yuexiu District, Guangzhou',
                'city' => 'Guangzhou',
                'state' => 'Guangdong',
                'postal_code' => '510000',
                'country' => 'CN',
                'is_default' => true,
                'shipping_company_name' => 'DHL',
                'shipping_method' => 'Express',
                'shipping_min_charge' => 0.00,
                'shipping_cost_per_kg' => 14.00,
                'shipping_base_cost' => 0.00,
                'shipping_additional_cost' => 0.00,
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
                'shipping_min_charge' => 0.00,
                'shipping_cost_per_kg' => 0.00,
                'shipping_base_cost' => 0.00,
                'shipping_additional_cost' => 0.00,
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
