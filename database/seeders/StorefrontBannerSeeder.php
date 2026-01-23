<?php

namespace Database\Seeders;

use App\Models\StorefrontBanner;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class StorefrontBannerSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::query()->where('is_active', true)->limit(3)->get();
        $products = Product::query()->where('is_active', true)->with('images')->limit(5)->get();

        $images = [
            'hero_home' => 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=1400&q=80',
            'hero_gadgets' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=1400&q=80',
            'carousel_kitchen' => 'https://images.unsplash.com/photo-1501045661006-fcebe0257c3f?auto=format&fit=crop&w=1200&q=80',
            'carousel_style' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=1200&q=80',
            'carousel_travel' => 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=1200&q=80',
            'sidebar_clearance' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=80',
            'sidebar_vip' => 'https://images.unsplash.com/photo-1521335629791-ce4aec67dd47?auto=format&fit=crop&w=900&q=80',
            'category_focus' => 'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?auto=format&fit=crop&w=1200&q=80',
            'product_focus' => 'https://images.unsplash.com/photo-1503602642458-232111445657?auto=format&fit=crop&w=1200&q=80',
        ];

        $banners = [
            [
                'title' => 'Logistics Support Week',
                'description' => 'Support for delivery costs on qualifying orders. Clear customs and duties before checkout.',
                'type' => 'informational',
                'display_type' => 'hero',
                'image_path' => $images['hero_home'],
                'target_type' => 'none',
                'background_color' => '#0f172a',
                'text_color' => '#ffffff',
                'badge_text' => 'SUPPORT',
                'badge_color' => '#f59e0b',
                'cta_text' => 'See promotions',
                'cta_url' => '/promotions',
                'starts_at' => Carbon::now(),
                'ends_at' => Carbon::now()->addDays(10),
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'title' => 'Flash Drops for Cote d\'Ivoire',
                'description' => 'Limited-time deals with tracked delivery and verified suppliers.',
                'type' => 'promotional',
                'display_type' => 'hero',
                'image_path' => $images['hero_gadgets'],
                'target_type' => 'none',
                'background_color' => '#111827',
                'text_color' => '#ffffff',
                'badge_text' => 'FLASH DEALS',
                'badge_color' => '#ef4444',
                'cta_text' => 'Shop drops',
                'cta_url' => '/promotions/products',
                'starts_at' => Carbon::now(),
                'ends_at' => Carbon::now()->addDays(5),
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'title' => 'Delivery support on orders over $50',
                'description' => 'We reduce shipping friction with predictable rates and tracking updates.',
                'type' => 'informational',
                'display_type' => 'strip',
                'target_type' => 'none',
                'background_color' => '#0ea5e9',
                'text_color' => '#ffffff',
                'badge_text' => 'LOGISTICS SUPPORT',
                'badge_color' => '#0f172a',
                'cta_text' => 'Learn more',
                'cta_url' => '/promotions',
                'starts_at' => Carbon::now(),
                'ends_at' => null,
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'title' => 'Kitchen wins for busy weeks',
                'description' => 'Small appliances and time-savers with reliable dispatch windows.',
                'type' => 'seasonal',
                'display_type' => 'carousel',
                'image_path' => $images['carousel_kitchen'],
                'target_type' => 'none',
                'background_color' => '#14b8a6',
                'text_color' => '#ffffff',
                'badge_text' => 'HOME ESSENTIALS',
                'badge_color' => '#0f172a',
                'cta_text' => 'Explore kitchen',
                'cta_url' => '/products',
                'starts_at' => Carbon::now(),
                'ends_at' => null,
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'title' => 'Style upgrades that ship together',
                'description' => 'Curated apparel and accessories with grouped delivery windows.',
                'type' => 'promotional',
                'display_type' => 'carousel',
                'image_path' => $images['carousel_style'],
                'target_type' => 'none',
                'background_color' => '#f97316',
                'text_color' => '#ffffff',
                'badge_text' => 'NEW ARRIVALS',
                'badge_color' => '#111827',
                'cta_text' => 'Shop style',
                'cta_url' => '/products',
                'starts_at' => Carbon::now(),
                'ends_at' => null,
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'title' => 'Travel-ready essentials',
                'description' => 'Gear up with smart picks and tracked delivery timelines.',
                'type' => 'promotional',
                'display_type' => 'carousel',
                'image_path' => $images['carousel_travel'],
                'target_type' => 'none',
                'background_color' => '#6366f1',
                'text_color' => '#ffffff',
                'badge_text' => 'TRAVEL EDIT',
                'badge_color' => '#111827',
                'cta_text' => 'Shop travel',
                'cta_url' => '/products',
                'starts_at' => Carbon::now(),
                'ends_at' => null,
                'is_active' => true,
                'display_order' => 3,
            ],
            [
                'title' => 'Clearance corner',
                'description' => 'Limited inventory with guaranteed tracking updates.',
                'type' => 'promotional',
                'display_type' => 'sidebar',
                'image_path' => $images['sidebar_clearance'],
                'target_type' => 'none',
                'background_color' => '#f59e0b',
                'text_color' => '#ffffff',
                'badge_text' => 'CLEARANCE',
                'badge_color' => '#111827',
                'cta_text' => 'Shop deals',
                'cta_url' => '/promotions/products',
                'starts_at' => Carbon::now(),
                'ends_at' => null,
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'title' => 'Join our insider list',
                'description' => 'Get delivery updates, early drops, and WhatsApp support reminders.',
                'type' => 'informational',
                'display_type' => 'sidebar',
                'image_path' => $images['sidebar_vip'],
                'target_type' => 'none',
                'background_color' => '#0f172a',
                'text_color' => '#ffffff',
                'badge_text' => 'INSIDER',
                'badge_color' => '#f59e0b',
                'cta_text' => 'Join now',
                'cta_url' => '/register',
                'starts_at' => Carbon::now(),
                'ends_at' => null,
                'is_active' => true,
                'display_order' => 2,
            ],
        ];

        if ($categories->count() > 0) {
            $category = $categories->first();
            $banners[] = [
                'title' => 'Best of ' . $category->name,
                'description' => 'Top-rated picks in ' . strtolower($category->name) . ' with tracked delivery.',
                'type' => 'category',
                'display_type' => 'carousel',
                'image_path' => $category->hero_image ?: $images['category_focus'],
                'target_type' => 'category',
                'category_id' => $category->id,
                'background_color' => '#8b5cf6',
                'text_color' => '#ffffff',
                'badge_text' => 'TRENDING',
                'badge_color' => '#111827',
                'cta_text' => 'Shop category',
                'cta_url' => '/categories/' . $category->id,
                'starts_at' => Carbon::now(),
                'ends_at' => Carbon::now()->addMonth(),
                'is_active' => true,
                'display_order' => 4,
            ];
        }

        if ($products->count() > 0) {
            $product = $products->first();
            $productImage = $product->images?->first()?->url ?: $images['product_focus'];

            $banners[] = [
                'title' => 'Featured: ' . $product->name,
                'description' => 'Limited stock available. Get yours before it\'s gone!',
                'type' => 'product',
                'display_type' => 'sidebar',
                'image_path' => $productImage,
                'target_type' => 'product',
                'product_id' => $product->id,
                'background_color' => '#0891b2',
                'text_color' => '#ffffff',
                'badge_text' => 'BEST SELLER',
                'badge_color' => '#111827',
                'cta_text' => 'View product',
                'cta_url' => '/products/' . $product->id,
                'starts_at' => Carbon::now(),
                'ends_at' => Carbon::now()->addDays(30),
                'is_active' => true,
                'display_order' => 3,
            ];
        }

        foreach ($banners as $banner) {
            StorefrontBanner::updateOrCreate(
                ['title' => $banner['title'], 'display_type' => $banner['display_type']],
                $banner
            );
        }

        $this->command->info('✓ Created ' . count($banners) . ' storefront banners with realistic data');
    }
}
