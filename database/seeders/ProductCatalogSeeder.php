<?php

namespace Database\Seeders;

use App\Domain\Products\Models\ProductImage;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categoryNames = [
            'Electronics',
            'Home and Kitchen',
            'Beauty and Care',
            'Fashion',
            'Baby and Kids',
            'Fitness and Outdoor',
            'Smart Home',
            'Office Essentials',
            'Travel and Luggage',
            'Auto and Tools',
        ];

        foreach ($categoryNames as $name) {
            Category::firstOrCreate(['name' => $name]);
        }

        if (Product::query()->exists()) {
            return;
        }

        $faker = fake();
        $categories = Category::query()->get();
        $positions = [1, 2, 3];

        foreach ($categories as $category) {
            $count = $faker->numberBetween(6, 10);

            for ($i = 0; $i < $count; $i++) {
                $name = $faker->unique()->words($faker->numberBetween(2, 4), true);
                $slugBase = Str::slug($name);
                $slug = $slugBase.'-'.Str::lower(Str::random(5));
                $selling = $faker->randomFloat(2, 18, 320);
                $cost = round($selling * $faker->randomFloat(2, 0.55, 0.82), 2);

                $product = Product::create([
                    'slug' => $slug,
                    'name' => Str::title($name),
                    'category_id' => $category->id,
                    'description' => $faker->paragraphs(2, true),
                    'selling_price' => $selling,
                    'cost_price' => $cost,
                    'status' => 'active',
                    'currency' => 'USD',
                    'default_fulfillment_provider_id' => null,
                    'supplier_id' => null,
                    'supplier_product_url' => null,
                    'shipping_estimate_days' => $faker->numberBetween(6, 18),
                    'is_active' => true,
                    'is_featured' => $faker->boolean(25),
                    'source_url' => null,
                    'options' => ['Color', 'Size'],
                    'attributes' => ['origin' => $faker->country()],
                ]);

                foreach ($positions as $position) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'url' => "https://picsum.photos/seed/{$slug}-{$position}/900/900",
                        'position' => $position,
                    ]);
                }
            }
        }
    }
}
