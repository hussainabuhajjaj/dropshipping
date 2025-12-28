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
        // Get some categories and products for targeting
        $categories = Category::limit(3)->get();
        $products = Product::limit(5)->get();

        $banners = [
            // Hero Banner - New Year Sale
            [
                'title' => 'New Year Mega Sale',
                'description' => 'Start 2026 with amazing deals! Up to 70% off on selected items. Free shipping on orders over $50.',
                'type' => 'promotional',
                'display_type' => 'hero',
                'image_path' => 'banners/new-year-hero.jpg',
                'target_type' => 'none',
                'background_color' => '#1e3a8a',
                'text_color' => '#ffffff',
                'badge_text' => 'LIMITED TIME',
                'badge_color' => '#ef4444',
                'cta_text' => 'Shop Now',
                'cta_url' => '/shop',
                'starts_at' => Carbon::now(),
                'ends_at' => Carbon::now()->addDays(7),
                'is_active' => true,
                'display_order' => 1,
            ],

            // Hero Banner - Flash Sale
            [
                'title' => '24-Hour Flash Sale',
                'description' => 'Lightning deals on trending products. Hurry before they\'re gone!',
                'type' => 'promotional',
                'display_type' => 'hero',
                'image_path' => 'banners/flash-sale-hero.jpg',
                'target_type' => 'none',
                'background_color' => '#dc2626',
                'text_color' => '#ffffff',
                'badge_text' => '24H ONLY',
                'badge_color' => '#fbbf24',
                'cta_text' => 'Grab Deals',
                'cta_url' => '/flash-sale',
                'starts_at' => Carbon::now(),
                'ends_at' => Carbon::now()->addDay(),
                'is_active' => true,
                'display_order' => 2,
            ],

            // Strip Banner - Free Shipping
            [
                'title' => 'Free Shipping on Orders Over $50',
                'description' => 'No minimum. No hidden fees. Shop now and save on delivery!',
                'type' => 'informational',
                'display_type' => 'strip',
                'target_type' => 'none',
                'background_color' => '#10b981',
                'text_color' => '#ffffff',
                'cta_text' => 'Shop Now',
                'cta_url' => '/shop',
                'starts_at' => Carbon::now(),
                'ends_at' => null,
                'is_active' => true,
                'display_order' => 1,
            ],

            // Strip Banner - New Arrivals
            [
                'title' => 'New Arrivals Just Dropped!',
                'description' => 'Be the first to shop our latest collection',
                'type' => 'promotional',
                'display_type' => 'strip',
                'target_type' => 'none',
                'background_color' => '#6366f1',
                'text_color' => '#ffffff',
                'cta_text' => 'Explore',
                'cta_url' => '/new-arrivals',
                'starts_at' => Carbon::now(),
                'ends_at' => Carbon::now()->addDays(14),
                'is_active' => true,
                'display_order' => 2,
            ],

            // Sidebar Banner - Clearance
            [
                'title' => 'Clearance Corner',
                'description' => 'Up to 80% off. Last chance to grab these deals!',
                'type' => 'promotional',
                'display_type' => 'sidebar',
                'image_path' => 'banners/clearance-sidebar.jpg',
                'target_type' => 'none',
                'background_color' => '#f59e0b',
                'text_color' => '#ffffff',
                'badge_text' => 'CLEARANCE',
                'badge_color' => '#dc2626',
                'cta_text' => 'Shop Sale',
                'cta_url' => '/clearance',
                'starts_at' => Carbon::now(),
                'ends_at' => null,
                'is_active' => true,
                'display_order' => 1,
            ],

            // Sidebar Banner - Member Benefits
            [
                'title' => 'Join Our VIP Club',
                'description' => 'Get exclusive access to member-only deals and early sales.',
                'type' => 'informational',
                'display_type' => 'sidebar',
                'image_path' => 'banners/vip-sidebar.jpg',
                'target_type' => 'none',
                'background_color' => '#7c3aed',
                'text_color' => '#ffffff',
                'badge_text' => 'VIP',
                'badge_color' => '#fbbf24',
                'cta_text' => 'Join Now',
                'cta_url' => '/register',
                'starts_at' => Carbon::now(),
                'ends_at' => null,
                'is_active' => true,
                'display_order' => 2,
            ],

            // Carousel Banner 1 - Winter Collection
            [
                'title' => 'Winter Essentials',
                'description' => 'Stay warm and stylish with our curated winter collection. Free returns on all items.',
                'type' => 'seasonal',
                'display_type' => 'carousel',
                'image_path' => 'banners/winter-carousel.jpg',
                'target_type' => 'none',
                'background_color' => '#0ea5e9',
                'text_color' => '#ffffff',
                'badge_text' => 'WINTER 2026',
                'badge_color' => '#3b82f6',
                'cta_text' => 'Explore Collection',
                'cta_url' => '/winter-collection',
                'starts_at' => Carbon::now(),
                'ends_at' => Carbon::create(2026, 2, 28),
                'is_active' => true,
                'display_order' => 1,
            ],

            // Carousel Banner 2 - Bundle Deals
            [
                'title' => 'Bundle & Save',
                'description' => 'Buy more, save more! Get up to 30% off when you bundle products together.',
                'type' => 'promotional',
                'display_type' => 'carousel',
                'image_path' => 'banners/bundle-carousel.jpg',
                'target_type' => 'none',
                'background_color' => '#ec4899',
                'text_color' => '#ffffff',
                'badge_text' => 'SAVE 30%',
                'badge_color' => '#dc2626',
                'cta_text' => 'View Bundles',
                'cta_url' => '/bundles',
                'starts_at' => Carbon::now(),
                'ends_at' => null,
                'is_active' => true,
                'display_order' => 2,
            ],

            // Carousel Banner 3 - Gift Guide
            [
                'title' => 'Perfect Gift Ideas',
                'description' => 'Find the perfect gift for everyone on your list. Gift wrapping available!',
                'type' => 'promotional',
                'display_type' => 'carousel',
                'image_path' => 'banners/gift-carousel.jpg',
                'target_type' => 'none',
                'background_color' => '#14b8a6',
                'text_color' => '#ffffff',
                'badge_text' => 'GIFT GUIDE',
                'badge_color' => '#f59e0b',
                'cta_text' => 'Browse Gifts',
                'cta_url' => '/gift-guide',
                'starts_at' => Carbon::now(),
                'ends_at' => null,
                'is_active' => true,
                'display_order' => 3,
            ],
        ];

        // Add category-specific banners if categories exist
        if ($categories->count() > 0) {
            $category = $categories->first();
            $banners[] = [
                'title' => 'Best of ' . $category->name,
                'description' => 'Discover top-rated products in ' . strtolower($category->name) . '. Hand-picked by our experts.',
                'type' => 'category',
                'display_type' => 'hero',
                'image_path' => 'banners/category-hero.jpg',
                'target_type' => 'category',
                'category_id' => $category->id,
                'background_color' => '#8b5cf6',
                'text_color' => '#ffffff',
                'badge_text' => 'TRENDING',
                'badge_color' => '#f59e0b',
                'cta_text' => 'Shop Category',
                'cta_url' => '/categories/' . $category->id,
                'starts_at' => Carbon::now(),
                'ends_at' => Carbon::now()->addMonth(),
                'is_active' => true,
                'display_order' => 3,
            ];
        }

        // Add product-specific banner if products exist
        if ($products->count() > 0) {
            $product = $products->first();
            $banners[] = [
                'title' => 'Featured: ' . $product->name,
                'description' => 'Limited stock available. Get yours before it\'s gone!',
                'type' => 'product',
                'display_type' => 'sidebar',
                'image_path' => 'banners/featured-product.jpg',
                'target_type' => 'product',
                'product_id' => $product->id,
                'background_color' => '#0891b2',
                'text_color' => '#ffffff',
                'badge_text' => 'BEST SELLER',
                'badge_color' => '#ef4444',
                'cta_text' => 'View Product',
                'cta_url' => '/products/' . $product->id,
                'starts_at' => Carbon::now(),
                'ends_at' => Carbon::now()->addDays(30),
                'is_active' => true,
                'display_order' => 3,
            ];
        }

        // Create all banners
        foreach ($banners as $banner) {
            StorefrontBanner::create($banner);
        }

        $this->command->info('✓ Created ' . count($banners) . ' storefront banners with realistic data');
    }
}
