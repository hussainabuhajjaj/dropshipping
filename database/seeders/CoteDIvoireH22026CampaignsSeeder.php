<?php

namespace Database\Seeders;

use App\Models\CampaignProductQuery;
use App\Models\StorefrontCampaign;
use App\Models\StorefrontCollection;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CoteDIvoireH22026CampaignsSeeder extends Seeder
{
    private bool $createCollections = false;
    private bool $skipExisting = true;

    public function run(bool $createCollections = false, bool $skipExisting = true): void
    {
        $this->createCollections = $createCollections;
        $this->skipExisting = $skipExisting;

        $campaigns = $this->getCampaigns();

        foreach ($campaigns as $data) {
            $campaignData = $data['campaign'];
            $slug = $campaignData['slug'];

            if ($this->skipExisting) {
                $existing = StorefrontCampaign::where('slug', $slug)->first();
                if ($existing) {
                    $this->command?->warn("Skipping existing campaign: {$campaignData['name']}");
                    continue;
                }
            }

            $campaign = StorefrontCampaign::create($campaignData);

            if (isset($data['productQuery'])) {
                $campaign->productQuery()->create($data['productQuery']);
            }

            $this->command?->info("Created campaign: {$campaign->name}");
        }
    }

    private function getCampaigns(): array
    {
        $tz = 'Africa/Abidjan';

        return [
            // ─── JULY 2026 ────────────────────────────────────────────
            [
                'campaign' => [
                    'name' => 'SOLDES D\'ÉTÉ 2026',
                    'slug' => 'soldes-dete-2026',
                    'type' => 'seasonal',
                    'status' => 'scheduled',
                    'is_active' => true,
                    'starts_at' => Carbon::parse('2026-07-05 00:00:00', $tz),
                    'ends_at' => Carbon::parse('2026-07-18 23:59:59', $tz),
                    'timezone' => $tz,
                    'priority' => 100,
                    'stacking_mode' => 'stackable',
                    'placements' => ['home_hero', 'home_carousel'],
                    'hero_kicker' => '🔥 -50%',
                    'hero_subtitle' => 'Les soldes d\'été sont là ! Jusqu\'à -50% sur les pièces mode. Stock limité.',
                    'locale_visibility' => ['en', 'fr'],
                    'locale_overrides' => [
                        ['locale' => 'fr', 'name' => 'SOLDES D\'ÉTÉ 2026', 'hero_kicker' => '🔥 -50%', 'hero_subtitle' => 'Les soldes d\'été sont là ! Jusqu\'à -50% sur les pièces mode. Stock limité.'],
                    ],
                    'sourcing_config' => [
                        'enabled' => true,
                        'sourcing_days_before' => 7,
                        'auto_create_collection' => true,
                        'override_home_sections' => ['featured'],
                    ],
                    'notification_config' => [
                        'on_start' => ['push' => true, 'email' => true, 'whatsapp' => true],
                        'on_ending_soon' => ['push' => true, 'email' => true, 'whatsapp' => false, 'hours_before' => 24],
                        'on_end' => ['push' => false, 'email' => false, 'whatsapp' => false],
                    ],
                ],
                'productQuery' => [
                    'keywords' => 'summer dress, bikini set, sandals women, shorts women, sunglasses',
                    'max_products' => 50,
                    'margin_percent' => 60,
                    'auto_activate' => true,
                    'sort_by' => 'sales',
                ],
            ],

            [
                'campaign' => [
                    'name' => 'Beauté Éclat',
                    'slug' => 'beaute-eclat',
                    'type' => 'seasonal',
                    'status' => 'scheduled',
                    'is_active' => true,
                    'starts_at' => Carbon::parse('2026-07-19 00:00:00', $tz),
                    'ends_at' => Carbon::parse('2026-07-25 23:59:59', $tz),
                    'timezone' => $tz,
                    'priority' => 60,
                    'stacking_mode' => 'stackable',
                    'placements' => ['home_hero', 'home_carousel'],
                    'hero_kicker' => '💇‍♀️ -20%',
                    'hero_subtitle' => 'Nouvelle collection beauté arrivée ! Perruques, tresses, maquillage — rayonnez cet été.',
                    'locale_visibility' => ['en', 'fr'],
                    'locale_overrides' => [
                        ['locale' => 'fr', 'name' => 'Beauté Éclat', 'hero_subtitle' => 'Nouvelle collection beauté arrivée ! Perruques, tresses, maquillage — rayonnez cet été.'],
                    ],
                    'sourcing_config' => [
                        'enabled' => true,
                        'sourcing_days_before' => 5,
                        'auto_create_collection' => true,
                        'override_home_sections' => ['featured'],
                    ],
                    'notification_config' => [
                        'on_start' => ['push' => true, 'email' => true, 'whatsapp' => true],
                        'on_ending_soon' => ['push' => true, 'email' => false, 'whatsapp' => false, 'hours_before' => 12],
                        'on_end' => ['push' => false, 'email' => false, 'whatsapp' => false],
                    ],
                ],
                'productQuery' => [
                    'keywords' => 'wig, hair extension, makeup set, skincare, false eyelashes, lipstick',
                    'max_products' => 40,
                    'margin_percent' => 60,
                    'auto_activate' => true,
                    'sort_by' => 'sales',
                ],
            ],

            // ─── AUGUST 2026 ───────────────────────────────────────────
            [
                'campaign' => [
                    'name' => 'Fierté Nationale',
                    'slug' => 'fierte-nationale-2026',
                    'type' => 'event',
                    'status' => 'scheduled',
                    'is_active' => true,
                    'starts_at' => Carbon::parse('2026-08-01 00:00:00', $tz),
                    'ends_at' => Carbon::parse('2026-08-07 23:59:59', $tz),
                    'timezone' => $tz,
                    'priority' => 90,
                    'stacking_mode' => 'exclusive',
                    'exclusive_group' => 'august-major',
                    'placements' => ['home_hero', 'home_carousel', 'home_strip'],
                    'hero_kicker' => '🇨🇮 Indépendance',
                    'hero_subtitle' => 'Fêtez l\'indépendance avec style ! Nos pièces aux couleurs de la Côte d\'Ivoire.',
                    'locale_visibility' => ['en', 'fr'],
                    'locale_overrides' => [
                        ['locale' => 'fr', 'name' => 'Fierté Nationale', 'hero_kicker' => '🇨🇮 Indépendance', 'hero_subtitle' => 'Fêtez l\'indépendance avec style ! Nos pièces aux couleurs de la Côte d\'Ivoire.'],
                    ],
                    'sourcing_config' => ['enabled' => false], // Seasonal no sourcing needed
                    'notification_config' => [
                        'on_start' => ['push' => true, 'email' => true, 'whatsapp' => true],
                        'on_ending_soon' => ['push' => true, 'email' => false, 'whatsapp' => false, 'hours_before' => 12],
                        'on_end' => ['push' => false, 'email' => false, 'whatsapp' => false],
                    ],
                ],
            ],

            [
                'campaign' => [
                    'name' => 'Rentrée Scolaire 2026',
                    'slug' => 'rentree-scolaire-2026',
                    'type' => 'seasonal',
                    'status' => 'scheduled',
                    'is_active' => true,
                    'starts_at' => Carbon::parse('2026-08-24 00:00:00', $tz),
                    'ends_at' => Carbon::parse('2026-09-06 23:59:59', $tz),
                    'timezone' => $tz,
                    'priority' => 85,
                    'stacking_mode' => 'stackable',
                    'placements' => ['home_hero', 'home_carousel'],
                    'hero_kicker' => '📚 -40%',
                    'hero_subtitle' => 'La rentrée c\'est maintenant ! Sacs, chaussures, vêtements — tout pour l\'école à prix doux.',
                    'locale_visibility' => ['en', 'fr'],
                    'locale_overrides' => [
                        ['locale' => 'fr', 'name' => 'Rentrée Scolaire 2026', 'hero_kicker' => '📚 -40%', 'hero_subtitle' => 'La rentrée c\'est maintenant ! Sacs, chaussures, vêtements — tout pour l\'école à prix doux.'],
                    ],
                    'sourcing_config' => [
                        'enabled' => true,
                        'sourcing_days_before' => 7,
                        'auto_create_collection' => true,
                        'override_home_sections' => ['featured'],
                    ],
                    'notification_config' => [
                        'on_start' => ['push' => true, 'email' => true, 'whatsapp' => true],
                        'on_ending_soon' => ['push' => true, 'email' => true, 'whatsapp' => false, 'hours_before' => 24],
                        'on_end' => ['push' => false, 'email' => false, 'whatsapp' => false],
                    ],
                ],
                'productQuery' => [
                    'keywords' => 'school bag, backpack kids, uniform, school shoes, stationery set, pencil case',
                    'max_products' => 40,
                    'margin_percent' => 50,
                    'auto_activate' => true,
                    'sort_by' => 'sales',
                ],
            ],

            // ─── SEPTEMBER 2026 ────────────────────────────────────────
            [
                'campaign' => [
                    'name' => 'Tech Days Septembre',
                    'slug' => 'tech-days-sept-2026',
                    'type' => 'event',
                    'status' => 'scheduled',
                    'is_active' => true,
                    'starts_at' => Carbon::parse('2026-09-14 00:00:00', $tz),
                    'ends_at' => Carbon::parse('2026-09-20 23:59:59', $tz),
                    'timezone' => $tz,
                    'priority' => 70,
                    'stacking_mode' => 'stackable',
                    'placements' => ['home_hero', 'home_carousel'],
                    'hero_kicker' => '📱 -25%',
                    'hero_subtitle' => 'Tech Days ! Coques, chargeurs, écouteurs, power banks — tout l\'essentiel tech à -25%.',
                    'locale_visibility' => ['en', 'fr'],
                    'locale_overrides' => [
                        ['locale' => 'fr', 'name' => 'Tech Days Septembre', 'hero_kicker' => '📱 -25%', 'hero_subtitle' => 'Tech Days ! Coques, chargeurs, écouteurs, power banks — tout l\'essentiel tech à -25%.'],
                    ],
                    'sourcing_config' => [
                        'enabled' => true,
                        'sourcing_days_before' => 5,
                        'auto_create_collection' => true,
                        'override_home_sections' => ['featured'],
                    ],
                    'notification_config' => [
                        'on_start' => ['push' => true, 'email' => true, 'whatsapp' => true],
                        'on_ending_soon' => ['push' => true, 'email' => false, 'whatsapp' => false, 'hours_before' => 12],
                        'on_end' => ['push' => false, 'email' => false, 'whatsapp' => false],
                    ],
                ],
                'productQuery' => [
                    'keywords' => 'phone case, charger, power bank, earphone, smart watch, bluetooth speaker',
                    'max_products' => 40,
                    'margin_percent' => 50,
                    'auto_activate' => true,
                    'sort_by' => 'sales',
                ],
            ],

            // ─── OCTOBER 2026 ───────────────────────────────────────────
            [
                'campaign' => [
                    'name' => 'Mode Urbaine',
                    'slug' => 'mode-urbaine-oct-2026',
                    'type' => 'seasonal',
                    'status' => 'scheduled',
                    'is_active' => true,
                    'starts_at' => Carbon::parse('2026-10-05 00:00:00', $tz),
                    'ends_at' => Carbon::parse('2026-10-11 23:59:59', $tz),
                    'timezone' => $tz,
                    'priority' => 65,
                    'stacking_mode' => 'stackable',
                    'placements' => ['home_hero', 'home_carousel'],
                    'hero_kicker' => '🔥 Streetwear',
                    'hero_subtitle' => 'Le streetwear débarque ! Sneakers, bags, caps — le look urbain qui fait la différence.',
                    'locale_visibility' => ['en', 'fr'],
                    'locale_overrides' => [
                        ['locale' => 'fr', 'name' => 'Mode Urbaine', 'hero_kicker' => '🔥 Streetwear', 'hero_subtitle' => 'Le streetwear débarque ! Sneakers, bags, caps — le look urbain qui fait la différence.'],
                    ],
                    'sourcing_config' => [
                        'enabled' => true,
                        'sourcing_days_before' => 5,
                        'auto_create_collection' => true,
                        'override_home_sections' => ['featured'],
                    ],
                    'notification_config' => [
                        'on_start' => ['push' => true, 'email' => true, 'whatsapp' => true],
                        'on_ending_soon' => ['push' => true, 'email' => false, 'whatsapp' => false, 'hours_before' => 12],
                        'on_end' => ['push' => false, 'email' => false, 'whatsapp' => false],
                    ],
                ],
                'productQuery' => [
                    'keywords' => 'sneakers men, sneakers women, cap, backpack, streetwear, hoodie, jogger pants',
                    'max_products' => 40,
                    'margin_percent' => 60,
                    'auto_activate' => true,
                    'sort_by' => 'sales',
                ],
            ],

            [
                'campaign' => [
                    'name' => 'Semaine du Bijou',
                    'slug' => 'semaine-du-bijou-2026',
                    'type' => 'seasonal',
                    'status' => 'scheduled',
                    'is_active' => true,
                    'starts_at' => Carbon::parse('2026-10-12 00:00:00', $tz),
                    'ends_at' => Carbon::parse('2026-10-18 23:59:59', $tz),
                    'timezone' => $tz,
                    'priority' => 60,
                    'stacking_mode' => 'stackable',
                    'placements' => ['home_hero', 'home_carousel'],
                    'hero_kicker' => '💎 -40%',
                    'hero_subtitle' => 'Semaine du bijou ! Jusqu\'à -40% sur bijoux, montres et bracelets.',
                    'locale_visibility' => ['en', 'fr'],
                    'locale_overrides' => [
                        ['locale' => 'fr', 'name' => 'Semaine du Bijou', 'hero_kicker' => '💎 -40%', 'hero_subtitle' => 'Semaine du bijou ! Jusqu\'à -40% sur bijoux, montres et bracelets.'],
                    ],
                    'sourcing_config' => [
                        'enabled' => true,
                        'sourcing_days_before' => 5,
                        'auto_create_collection' => true,
                        'override_home_sections' => ['featured'],
                    ],
                    'notification_config' => [
                        'on_start' => ['push' => true, 'email' => true, 'whatsapp' => true],
                        'on_ending_soon' => ['push' => true, 'email' => false, 'whatsapp' => false, 'hours_before' => 12],
                        'on_end' => ['push' => false, 'email' => false, 'whatsapp' => false],
                    ],
                ],
                'productQuery' => [
                    'keywords' => 'necklace, earring, bracelet, ring, watch women, watch men, jewelry set',
                    'max_products' => 40,
                    'margin_percent' => 65,
                    'auto_activate' => true,
                    'sort_by' => 'sales',
                ],
            ],

            // ─── NOVEMBER 2026 ──────────────────────────────────────────
            [
                'campaign' => [
                    'name' => 'Semaine de la Paix',
                    'slug' => 'semaine-de-la-paix-2026',
                    'type' => 'event',
                    'status' => 'scheduled',
                    'is_active' => true,
                    'starts_at' => Carbon::parse('2026-11-09 00:00:00', $tz),
                    'ends_at' => Carbon::parse('2026-11-16 23:59:59', $tz),
                    'timezone' => $tz,
                    'priority' => 80,
                    'stacking_mode' => 'stackable',
                    'placements' => ['home_hero', 'home_strip'],
                    'hero_kicker' => '🕊️ Paix',
                    'hero_subtitle' => 'Célébrons la Paix ensemble. Collection spéciale et livraison offerte avec le code PAIX2026.',
                    'locale_visibility' => ['en', 'fr'],
                    'locale_overrides' => [
                        ['locale' => 'fr', 'name' => 'Semaine de la Paix', 'hero_kicker' => '🕊️ Paix', 'hero_subtitle' => 'Célébrons la Paix ensemble. Collection spéciale et livraison offerte avec le code PAIX2026.'],
                    ],
                    'sourcing_config' => ['enabled' => false],
                    'notification_config' => [
                        'on_start' => ['push' => true, 'email' => true, 'whatsapp' => false],
                        'on_ending_soon' => ['push' => true, 'email' => false, 'whatsapp' => false, 'hours_before' => 12],
                        'on_end' => ['push' => false, 'email' => false, 'whatsapp' => false],
                    ],
                ],
            ],

            [
                'campaign' => [
                    'name' => 'BLACK FRIDAY 2026 ⭐',
                    'slug' => 'black-friday-2026',
                    'type' => 'event',
                    'status' => 'scheduled',
                    'is_active' => true,
                    'starts_at' => Carbon::parse('2026-11-27 00:00:00', $tz),
                    'ends_at' => Carbon::parse('2026-11-29 23:59:59', $tz),
                    'timezone' => $tz,
                    'priority' => 200,
                    'stacking_mode' => 'exclusive',
                    'exclusive_group' => 'black-friday',
                    'placements' => ['home_hero', 'home_carousel', 'home_strip', 'home_popup'],
                    'hero_kicker' => '🔥 BLACK FRIDAY 🔥',
                    'hero_subtitle' => 'Jusqu\'à -70% sur tout le site ! Mode, beauté, électronique, maison... 3 jours de folie !',
                    'locale_visibility' => ['en', 'fr'],
                    'locale_overrides' => [
                        ['locale' => 'fr', 'name' => 'BLACK FRIDAY 2026 ⭐', 'hero_kicker' => '🔥 BLACK FRIDAY 🔥', 'hero_subtitle' => 'Jusqu\'à -70% sur tout le site ! Mode, beauté, électronique, maison... 3 jours de folie !'],
                    ],
                    'sourcing_config' => ['enabled' => false],
                    'notification_config' => [
                        'on_start' => ['push' => true, 'email' => true, 'whatsapp' => true],
                        'on_ending_soon' => ['push' => true, 'email' => true, 'whatsapp' => true, 'hours_before' => 6],
                        'on_end' => ['push' => false, 'email' => false, 'whatsapp' => false],
                    ],
                ],
            ],

            // ─── DECEMBER 2026 ──────────────────────────────────────────
            [
                'campaign' => [
                    'name' => 'Tenues de Fête',
                    'slug' => 'tenues-de-fete-2026',
                    'type' => 'seasonal',
                    'status' => 'scheduled',
                    'is_active' => true,
                    'starts_at' => Carbon::parse('2026-12-07 00:00:00', $tz),
                    'ends_at' => Carbon::parse('2026-12-18 23:59:59', $tz),
                    'timezone' => $tz,
                    'priority' => 90,
                    'stacking_mode' => 'stackable',
                    'placements' => ['home_hero', 'home_carousel'],
                    'hero_kicker' => '✨ Fêtes',
                    'hero_subtitle' => 'La saison des fêtes commence ! Robes, costumes, bijoux — brillez lors de vos fêtes de fin d\'année.',
                    'locale_visibility' => ['en', 'fr'],
                    'locale_overrides' => [
                        ['locale' => 'fr', 'name' => 'Tenues de Fête', 'hero_kicker' => '✨ Fêtes', 'hero_subtitle' => 'La saison des fêtes commence ! Robes, costumes, bijoux — brillez lors de vos fêtes de fin d\'année.'],
                    ],
                    'sourcing_config' => [
                        'enabled' => true,
                        'sourcing_days_before' => 7,
                        'auto_create_collection' => true,
                        'override_home_sections' => ['featured'],
                    ],
                    'notification_config' => [
                        'on_start' => ['push' => true, 'email' => true, 'whatsapp' => true],
                        'on_ending_soon' => ['push' => true, 'email' => true, 'whatsapp' => false, 'hours_before' => 24],
                        'on_end' => ['push' => false, 'email' => false, 'whatsapp' => false],
                    ],
                ],
                'productQuery' => [
                    'keywords' => 'evening dress, cocktail dress, suit men, party jewelry, high heels, formal shoes',
                    'max_products' => 50,
                    'margin_percent' => 60,
                    'auto_activate' => true,
                    'sort_by' => 'sales',
                ],
            ],

            [
                'campaign' => [
                    'name' => 'Joyeux Noël 2026',
                    'slug' => 'joyeux-noel-2026',
                    'type' => 'seasonal',
                    'status' => 'scheduled',
                    'is_active' => true,
                    'starts_at' => Carbon::parse('2026-12-19 00:00:00', $tz),
                    'ends_at' => Carbon::parse('2026-12-25 23:59:59', $tz),
                    'timezone' => $tz,
                    'priority' => 100,
                    'stacking_mode' => 'exclusive',
                    'exclusive_group' => 'december-holiday',
                    'placements' => ['home_hero', 'home_carousel', 'home_strip'],
                    'hero_kicker' => '🎄 Noël',
                    'hero_subtitle' => '🎄 Joyeux Noël ! Trouvez le cadeau parfait avec -20% sur tout le site. Code NOEL20.',
                    'locale_visibility' => ['en', 'fr'],
                    'locale_overrides' => [
                        ['locale' => 'fr', 'name' => 'Joyeux Noël 2026', 'hero_kicker' => '🎄 Noël', 'hero_subtitle' => '🎄 Joyeux Noël ! Trouvez le cadeau parfait avec -20% sur tout le site. Code NOEL20.'],
                    ],
                    'sourcing_config' => ['enabled' => false],
                    'notification_config' => [
                        'on_start' => ['push' => true, 'email' => true, 'whatsapp' => true],
                        'on_ending_soon' => ['push' => true, 'email' => true, 'whatsapp' => true, 'hours_before' => 12],
                        'on_end' => ['push' => true, 'email' => true, 'whatsapp' => true],
                    ],
                ],
            ],

            [
                'campaign' => [
                    'name' => 'Soldes de Fin d\'Année',
                    'slug' => 'soldes-fin-annee-2026',
                    'type' => 'seasonal',
                    'status' => 'scheduled',
                    'is_active' => true,
                    'starts_at' => Carbon::parse('2026-12-26 00:00:00', $tz),
                    'ends_at' => Carbon::parse('2026-12-31 23:59:59', $tz),
                    'timezone' => $tz,
                    'priority' => 80,
                    'stacking_mode' => 'stackable',
                    'placements' => ['home_hero', 'home_carousel'],
                    'hero_kicker' => '🎆 -60%',
                    'hero_subtitle' => 'Soldes géants de fin d\'année ! Jusqu\'à -60% pour terminer 2026 en beauté.',
                    'locale_visibility' => ['en', 'fr'],
                    'locale_overrides' => [
                        ['locale' => 'fr', 'name' => 'Soldes de Fin d\'Année', 'hero_kicker' => '🎆 -60%', 'hero_subtitle' => 'Soldes géants de fin d\'année ! Jusqu\'à -60% pour terminer 2026 en beauté.'],
                    ],
                    'sourcing_config' => ['enabled' => false],
                    'notification_config' => [
                        'on_start' => ['push' => true, 'email' => true, 'whatsapp' => true],
                        'on_ending_soon' => ['push' => true, 'email' => false, 'whatsapp' => false, 'hours_before' => 12],
                        'on_end' => ['push' => false, 'email' => false, 'whatsapp' => false],
                    ],
                ],
            ],
        ];
    }
}
