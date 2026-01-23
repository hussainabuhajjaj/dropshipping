<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\StorefrontBanner;
use App\Models\StorefrontCampaign;
use App\Models\StorefrontCollection;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StorefrontSeasonalSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        StorefrontCampaign::truncate();
        StorefrontCollection::truncate();
        StorefrontBanner::truncate();
        Promotion::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $categories = Category::query()->where('is_active', true)->orderByDesc('created_at')->get();
        $products = Product::query()->where('is_active', true)->with('images')->orderByDesc('created_at')->limit(6)->get();

        $pickCategories = function (array $keywords, int $fallback = 3) use ($categories) {
            $matches = $categories->filter(function ($category) use ($keywords) {
                $needle = mb_strtolower($category->name ?? '');
                return collect($keywords)->contains(fn ($keyword) => str_contains($needle, mb_strtolower($keyword)));
            })->pluck('id')->values()->all();

            if (empty($matches)) {
                $matches = $categories->pluck('id')->take($fallback)->values()->all();
            }

            return $matches;
        };

        $collections = [
            [
                'slug' => 'journey-tech-edit',
                'title' => 'Journey Tech Edit',
                'description' => 'Portable power, travel-ready audio, and conference-grade connectivity curated for digital nomads.',
                'type' => 'seasonal',
                'hero_kicker' => 'Remote ready',
                'hero_subtitle' => 'Tech that keeps you productive anywhere with transparent logistics.',
                'hero_image' => 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=1400&q=80',
                'hero_cta_label' => 'Build your kit',
                'hero_cta_url' => '/collections/journey-tech-edit',
                'content' => '<p>Expert-picked travel tech, power stations, and noise-canceling essentials that ship globally with tracking after every dispatch.</p>',
                'rules' => [
                    'category_ids' => $pickCategories(['Man Wallets', 'Bridal Jewelry Sets', 'Pumps']),
                    'in_stock' => true,
                    'min_price' => 30,
                    'max_price' => 450,
                    'sort' => 'newest',
                ],
                'selection_mode' => 'rules',
                'product_limit' => 20,
                'sort_by' => 'featured',
                'starts_at' => $now->subDays(3),
                'ends_at' => $now->addDays(30),
                'timezone' => 'UTC',
                'locale_visibility' => ['en', 'fr'],
                'display_order' => 1,
                'locale_overrides' => [
                    [
                        'locale' => 'fr',
                        'title' => 'Sélection Voyage',
                        'description' => 'Tech nomade avec batteries longue durée et livraison suivie.',
                        'hero_kicker' => 'Prêt à voyager',
                        'hero_subtitle' => 'Gadgets mobiles et connexions fiables où que vous soyez.',
                    ],
                ],
            ],
            [
                'slug' => 'global-home-lab',
                'title' => 'Global Home Lab',
                'description' => 'Design-forward living essentials, smart kitchen, and ambient lighting sourced for every country.',
                'type' => 'collection',
                'hero_kicker' => 'Elevate home',
                'hero_subtitle' => 'Curated makes for cozy nights, hybrid work, and boutique hospitality moments.',
                'hero_image' => 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=1400&q=80',
                'hero_cta_label' => 'Style it now',
                'hero_cta_url' => '/collections/global-home-lab',
                'content' => '<p>Gifted home kits, curated decor, and reliable appliances with full duty disclosures for every delivery lane.</p>',
                'rules' => [
                    'category_ids' => $pickCategories(['home', 'decor', 'kitchen']),
                    'in_stock' => true,
                    'min_price' => 15,
                    'max_price' => 320,
                    'sort' => 'featured',
                ],
                'selection_mode' => 'hybrid',
                'manual_products' => $products->map(fn ($product, $index) => [
                    'product_id' => $product->id,
                    'position' => $index,
                ])->values()->all(),
                'product_limit' => 24,
                'sort_by' => 'popularity',
                'starts_at' => $now->subDays(2),
                'ends_at' => $now->addDays(45),
                'timezone' => 'UTC',
                'locale_visibility' => ['en', 'fr'],
                'display_order' => 2,
                'locale_overrides' => [
                    [
                        'locale' => 'fr',
                        'title' => 'Laboratoire Maison',
                        'description' => 'Une sélection cosy qui combine déco et appareils fiables.',
                        'hero_kicker' => 'Maison moderne',
                        'hero_subtitle' => 'Pièces design et gadgets de confort prête à livrer.',
                    ],
                ],
            ],
            [
                'slug' => 'wellness-luxe-edit',
                'title' => 'Wellness Luxe Edit',
                'description' => 'Self-care capsules, beauty tech, and curated spa essentials with fast dispatch.',
                'type' => 'drop',
                'hero_kicker' => 'Wellness focus',
                'hero_subtitle' => 'Minimal rituals and luxury skincare selected for global markets.',
                'hero_image' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1400&q=80',
                'hero_cta_label' => 'Shop the ritual',
                'hero_cta_url' => '/collections/wellness-luxe-edit',
                'content' => '<p>Pure ingredients, designer prep kits, and skincare drops from reputable partners—delivered with customs clarity.</p>',
                'rules' => [
                    'category_ids' => $pickCategories(['beauty', 'wellness', 'skincare']),
                    'in_stock' => true,
                    'min_price' => 12,
                    'max_price' => 260,
                    'sort' => 'rating',
                ],
                'selection_mode' => 'rules',
                'product_limit' => 12,
                'sort_by' => 'rating',
                'starts_at' => $now->subDays(5),
                'ends_at' => $now->addDays(20),
                'timezone' => 'UTC',
                'locale_visibility' => ['en', 'fr'],
                'display_order' => 3,
                'locale_overrides' => [
                    [
                        'locale' => 'fr',
                        'title' => 'Curation Bien-Être',
                        'description' => 'Rituels et soins premium pour un voyage sensoriel.',
                        'hero_kicker' => 'Rituel luxe',
                        'hero_subtitle' => 'Soins raffinés avec livraison suivie.',
                    ],
                ],
            ],
        ];

        $collectionIndex = [];
        foreach ($collections as $payload) {
            $collection = StorefrontCollection::updateOrCreate(
                ['slug' => $payload['slug']],
                $payload
            );
            $collectionIndex[$payload['slug']] = $collection;
        }

        $bannerPayloads = [
            'winter-hero' => [
                'title' => 'Winter Courier Spotlight',
                'description' => 'Premium winter essentials staging with dedicated logistics timelines.',
                'type' => 'seasonal',
                'display_type' => 'hero',
                'image_path' => 'https://images.unsplash.com/photo-1484704849700-f032a568e944?auto=format&fit=crop&w=1400&q=80',
                'target_type' => 'none',
                'background_color' => '#0f172a',
                'text_color' => '#fcf8f3',
                'badge_text' => 'WINTER 2026',
                'badge_color' => '#fbbf24',
                'cta_text' => 'Explore winter drops',
                'cta_url' => '/promotions',
                'starts_at' => $now->subDay(),
                'ends_at' => $now->addDays(30),
                'is_active' => true,
                'display_order' => 1,
                'targeting' => ['image_mode' => 'cover'],
            ],
            'winter-carousel' => [
                'title' => 'Cozy Drops Carousel',
                'description' => 'Carousel of winter-ready fashion, home, and gifting.',
                'type' => 'seasonal',
                'display_type' => 'carousel',
                'image_path' => 'https://images.unsplash.com/photo-1470337458703-46ad1756a187?auto=format&fit=crop&w=1300&q=80',
                'target_type' => 'none',
                'background_color' => '#111827',
                'text_color' => '#ffffff',
                'badge_text' => 'COZY WEEKS',
                'badge_color' => '#f97316',
                'cta_text' => 'Shop seasons',
                'cta_url' => '/products',
                'starts_at' => $now->subDay(),
                'ends_at' => $now->addDays(30),
                'is_active' => true,
                'display_order' => 2,
                'targeting' => ['image_mode' => 'image_only'],
            ],
            'valentine-carousel' => [
                'title' => 'Valentine Glow Carousel',
                'description' => 'Romantic gifting, beauty, and fragrance curated for intimate drops.',
                'type' => 'event',
                'display_type' => 'carousel',
                'image_path' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1300&q=80',
                'target_type' => 'none',
                'background_color' => '#7c3aed',
                'text_color' => '#ffffff',
                'badge_text' => 'VALENTINE',
                'badge_color' => '#f43f5e',
                'cta_text' => 'Shop romantic gifts',
                'cta_url' => '/collections/wellness-luxe-edit',
                'starts_at' => $now->subDays(1),
                'ends_at' => $now->addDays(10),
                'is_active' => true,
                'display_order' => 3,
                'targeting' => ['image_mode' => 'cover'],
            ],
            'summer-strip' => [
                'title' => 'Summer launch strip',
                'description' => 'New linen, travel-ready accessories, and coastal palettes.',
                'type' => 'seasonal',
                'display_type' => 'strip',
                'image_path' => 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1300&q=80',
                'target_type' => 'none',
                'background_color' => '#047857',
                'text_color' => '#ffffff',
                'badge_text' => 'SUMMER',
                'badge_color' => '#f8fafc',
                'cta_text' => 'See summer drops',
                'cta_url' => '/collections/global-home-lab',
                'starts_at' => $now,
                'ends_at' => $now->addDays(40),
                'is_active' => true,
                'display_order' => 4,
                'targeting' => ['image_mode' => 'cover'],
            ],
        ];

        $bannerIndex = [];
        foreach ($bannerPayloads as $key => $payload) {
            $bannerIndex[$key] = StorefrontBanner::create($payload);
        }

        $campaigns = [
            [
                'slug' => 'winter-courier-festival',
                'name' => 'Winter Courier Festival',
                'kicker' => 'Winter dispatch',
                'subtitle' => 'Festive global drops with verified shipping windows.',
                'type' => 'seasonal',
                'priority' => 20,
                'stacking_mode' => 'exclusive',
                'exclusive_group' => 'seasonal-winter',
                'hero_image' => 'https://images.unsplash.com/photo-1484704849700-f032a568e944?auto=format&fit=crop&w=1400&q=80',
                'placements' => ['home_hero', 'home_carousel', 'home_strip', 'promotions_page'],
                'banner_refs' => ['winter-hero', 'winter-carousel'],
                'collection_refs' => ['journey-tech-edit', 'global-home-lab'],
                'theme' => [
                    'primary' => '#0f172a',
                    'secondary' => '#1e3a8a',
                    'accent' => '#fbbf24',
                    'image_mode' => 'cover',
                ],
            ],
            [
                'slug' => 'valentine-glow-drop',
                'name' => 'Valentine Glow Drop',
                'kicker' => 'Valentines',
                'subtitle' => 'Beauty, fragrance, and cozy sets for romance-ready gifting.',
                'type' => 'event',
                'priority' => 14,
                'stacking_mode' => 'stackable',
                'hero_image' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1400&q=80',
                'placements' => ['home_carousel', 'collections_index', 'promotions_page'],
                'banner_refs' => ['valentine-carousel'],
                'collection_refs' => ['wellness-luxe-edit'],
                'theme' => [
                    'primary' => '#7c3aed',
                    'secondary' => '#a855f7',
                    'accent' => '#f43f5e',
                    'image_mode' => 'split',
                ],
            ],
            [
                'slug' => 'summer-lantern-2026',
                'name' => 'Summer Lantern 2026',
                'kicker' => 'Summer',
                'subtitle' => 'Coastal palettes, sustainable linen, and coastal kits.',
                'type' => 'drop',
                'priority' => 10,
                'stacking_mode' => 'stackable',
                'hero_image' => 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1400&q=80',
                'placements' => ['home_strip', 'promotions_page'],
                'banner_refs' => ['summer-strip'],
                'collection_refs' => ['global-home-lab'],
                'theme' => [
                    'primary' => '#047857',
                    'secondary' => '#059669',
                    'accent' => '#fef9c3',
                    'image_mode' => 'cover',
                ],
            ],
        ];

        foreach ($campaigns as $payload) {
            $bannerIds = collect($payload['banner_refs'] ?? [])
                ->map(fn ($handle) => $bannerIndex[$handle] ?? null)
                ->filter()
                ->pluck('id')
                ->values()
                ->all();

            $collectionIds = collect($payload['collection_refs'] ?? [])
                ->map(fn ($slug) => $collectionIndex[$slug] ?? null)
                ->filter()
                ->pluck('id')
                ->values()
                ->all();

            StorefrontCampaign::updateOrCreate(
                ['slug' => $payload['slug']],
                [
                    'name' => $payload['name'],
                    'type' => $payload['type'],
                    'status' => 'active',
                    'is_active' => true,
                    'starts_at' => $now->subDay(),
                    'ends_at' => $now->addDays(30),
                    'timezone' => 'UTC',
                    'locale_visibility' => ['en', 'fr'],
                    'priority' => $payload['priority'],
                    'stacking_mode' => $payload['stacking_mode'],
                    'exclusive_group' => $payload['exclusive_group'] ?? null,
                    'hero_kicker' => $payload['kicker'],
                    'hero_subtitle' => $payload['subtitle'],
                    'hero_image' => $payload['hero_image'],
                    'placements' => $payload['placements'],
                    'banner_ids' => $bannerIds,
                    'collection_ids' => $collectionIds,
                    'theme' => $payload['theme'],
                ]
            );
        }

        $this->command?->info('Rebuilt storefront seasonals, campaigns, and banners with fresh data.');
    }
}
