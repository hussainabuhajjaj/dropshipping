<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Products\Models\ProductImage;
use App\Domain\Products\Models\ProductVariant;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class CJSeeder extends Seeder
{
    public function run(): void
    {
        FulfillmentProvider::updateOrCreate(
            [
                'id' => 1,
            ],
            [
                'code' => 'cj',
                'name' => 'CJ Dropshipping',
                'type' => 'supplier',
                'driver_class' => \App\Domain\Fulfillment\Strategies\CJDropshippingFulfillmentStrategy::class,
                'settings' => [
                    'warehouse_id' => null,
                    'shipping_method' => null,
                    'logistics_type' => null,
                ],
                'credentials' => [],
                'contact_info' => ['channel' => 'api'],
                'notes' => 'Default CJ provider. Set settings/credentials as needed.',
                'is_active' => true,
                'is_blacklisted' => false,
                'retry_limit' => 3,
            ]
        );

//        $parent = Category::firstOrCreate(
//            ['cj_id' => 'CJ-1000'],
//            [
//                'name' => 'CJ Essentials',
//                'slug' => 'cj-essentials',
//                'description' => 'Top CJ categories for quick import.',
//                'hero_title' => 'CJ Essentials',
//                'hero_subtitle' => 'Trending CJ items ready for import.',
//            ]
//        );
//
//        $subcategories = [
//            ['cj_id' => 'CJ-1100', 'name' => 'CJ Home', 'slug' => 'cj-home'],
//            ['cj_id' => 'CJ-1200', 'name' => 'CJ Gadgets', 'slug' => 'cj-gadgets'],
//            ['cj_id' => 'CJ-1300', 'name' => 'CJ Beauty', 'slug' => 'cj-beauty'],
//        ];
//
//        $categoryMap = [];
//        foreach ($subcategories as $entry) {
//            $category = Category::firstOrCreate(
//                ['cj_id' => $entry['cj_id']],
//                [
//                    'name' => $entry['name'],
//                    'slug' => $entry['slug'],
//                    'parent_id' => $parent->id,
//                    'description' => 'Seeded CJ subcategory.',
//                ]
//            );
//            $categoryMap[$entry['cj_id']] = $category;
//        }
//
//        $products = [
//            [
//                'cj_pid' => 'CJ-PID-0001',
//                'name' => 'CJ Aroma Diffuser',
//                'category_id' => $categoryMap['CJ-1100']->id ?? $parent->id,
//                'price' => 34.9,
//                'compare_at_price' => 49.9,
//                'images' => [
//                    'https://picsum.photos/seed/cj-diffuser/900/900',
//                    'https://picsum.photos/seed/cj-diffuser-2/900/900',
//                ],
//                'variants' => [
//                    ['cj_vid' => 'CJ-VID-0001', 'title' => 'Wood Finish', 'price' => 34.9],
//                    ['cj_vid' => 'CJ-VID-0002', 'title' => 'White Finish', 'price' => 34.9],
//                ],
//            ],
//            [
//                'cj_pid' => 'CJ-PID-0002',
//                'name' => 'CJ Smart Charger',
//                'category_id' => $categoryMap['CJ-1200']->id ?? $parent->id,
//                'price' => 22.5,
//                'compare_at_price' => 29.0,
//                'images' => [
//                    'https://picsum.photos/seed/cj-charger/900/900',
//                ],
//                'variants' => [
//                    ['cj_vid' => 'CJ-VID-0003', 'title' => 'USB-C', 'price' => 22.5],
//                ],
//            ],
//            [
//                'cj_pid' => 'CJ-PID-0003',
//                'name' => 'CJ Skincare Set',
//                'category_id' => $categoryMap['CJ-1300']->id ?? $parent->id,
//                'price' => 41.0,
//                'compare_at_price' => 55.0,
//                'images' => [
//                    'https://picsum.photos/seed/cj-skincare/900/900',
//                ],
//                'variants' => [
//                    ['cj_vid' => 'CJ-VID-0004', 'title' => 'Standard Pack', 'price' => 41.0],
//                ],
//            ],
//        ];
//
//        foreach ($products as $entry) {
//            $slug = Str::slug($entry['name']);
//            $product = Product::firstOrCreate(
//                ['cj_pid' => $entry['cj_pid']],
//                [
//                    'slug' => $slug,
//                    'name' => $entry['name'],
//                    'category_id' => $entry['category_id'],
//                    'description' => $entry['name'] . ' imported from CJ.',
//                    'selling_price' => $entry['price'],
//                    'cost_price' => round($entry['price'] * 0.6, 2),
//                    'status' => 'active',
//                    'currency' => 'USD',
//                    'is_active' => true,
//                    'is_featured' => true,
//                    'cj_sync_enabled' => true,
//                    'cj_synced_at' => now()->subHours(2),
//                    'cj_last_payload' => ['seeded' => true, 'source' => 'CJSeeder'],
//                    'cj_last_changed_fields' => ['name', 'price', 'images'],
//                    'cj_video_urls' => [],
//                ]
//            );
//
//            foreach ($entry['images'] as $index => $url) {
//                ProductImage::firstOrCreate(
//                    ['product_id' => $product->id, 'url' => $url],
//                    ['position' => $index + 1]
//                );
//            }
//
//            foreach ($entry['variants'] as $variant) {
//                ProductVariant::firstOrCreate(
//                    ['product_id' => $product->id, 'cj_vid' => $variant['cj_vid']],
//                    [
//                        'sku' => $variant['cj_vid'],
//                        'title' => $variant['title'],
//                        'price' => $variant['price'],
//                        'compare_at_price' => $entry['compare_at_price'],
//                        'cost_price' => round($variant['price'] * 0.6, 2),
//                        'currency' => 'USD',
//                        'inventory_policy' => 'allow',
//                        'options' => ['Variant' => $variant['title']],
//                    ]
//                );
//            }
//        }
    }
}
