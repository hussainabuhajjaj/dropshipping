<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\HomePageSetting;
use Illuminate\Database\Seeder;

class HomePageSettingSeeder extends Seeder
{
    public function run(): void
    {
        $categoryHighlights = Category::query()
            ->where('is_active', true)
            ->whereHas('products', fn ($q) => $q->where('is_active', true))
            ->orderByDesc('view_count')
            ->limit(8)
            ->pluck('id')
            ->map(fn (int $id) => ['category_id' => $id])
            ->values()
            ->all();

        $payload = [
            'top_strip' => [
                ['icon' => '24H', 'title' => 'Daily flash drops', 'subtitle' => 'Fresh deals with verified dispatch windows.'],
                ['icon' => 'FAST', 'title' => 'Fast dispatch', 'subtitle' => 'Suppliers confirm in 24-48 hours.'],
                ['icon' => 'CLEAR', 'title' => 'Customs clarity', 'subtitle' => 'Duties shown before checkout.'],
            ],
            'hero_slides' => [
                [
                    'kicker' => 'New season picks',
                    'title' => 'Upgrade your home with confident delivery',
                    'subtitle' => 'Curated essentials, verified suppliers, and transparent customs for Cote d\'Ivoire.',
                    'image' => 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=1200&q=80',
                    'primary_label' => 'Shop arrivals',
                    'primary_href' => '/products',
                    'secondary_label' => 'Track order',
                    'secondary_href' => '/orders/track',
                    'meta' => ['Fast dispatch', 'Duty clarity', 'Reliable tracking'],
                ],
                [
                    'kicker' => 'Bundle-ready',
                    'title' => 'Bundle picks for tech, travel, and self care',
                    'subtitle' => 'High-impact essentials that ship together for easier delivery.',
                    'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=1200&q=80',
                    'primary_label' => 'Browse bundles',
                    'primary_href' => '/products',
                    'secondary_label' => 'See categories',
                    'secondary_href' => '/products',
                    'meta' => ['Bundle savings', 'Verified stock', 'Clear timelines'],
                ],
                [
                    'kicker' => 'Smart sourcing',
                    'title' => 'Everyday heroes delivered with customs clarity',
                    'subtitle' => 'Shop top-rated essentials without surprises at checkout.',
                    'image' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=1200&q=80',
                    'primary_label' => 'Shop essentials',
                    'primary_href' => '/products',
                    'secondary_label' => 'Support',
                    'secondary_href' => '/support',
                    'meta' => ['No hidden fees', 'WhatsApp support', 'Trusted suppliers'],
                ],
            ],
            'rail_cards' => [
                [
                    'kicker' => 'Offers',
                    'title' => 'Weekend mega picks',
                    'subtitle' => 'Bundles, gadgets, and home upgrades with fast dispatch.',
                    'cta' => 'Shop offers',
                    'href' => '/products',
                ],
                [
                    'kicker' => 'Collections',
                    'title' => 'Smart home revamp',
                    'subtitle' => 'Energy-saving essentials curated for everyday comfort.',
                    'cta' => 'Browse collection',
                    'href' => '/products',
                ],
            ],
            'category_highlights' => $categoryHighlights,
            'banner_strip' => [
                'kicker' => 'Simbazu picks',
                'title' => 'Upgrade every room with clear delivery timelines',
                'cta' => 'Explore home upgrades',
                'href' => '/products',
            ],
        ];

        $existing = HomePageSetting::query()->latest()->first();

        if ($existing) {
            $existing->update($payload);
        } else {
            HomePageSetting::query()->create($payload);
        }
    }
}
