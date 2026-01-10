<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FullTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            ProductCatalogSeeder::class,
            CustomerPerksSeeder::class,
            CJSeeder::class,
            TestDataSeeder::class,
            PromotionSeeder::class,
        ]);
    }
}
