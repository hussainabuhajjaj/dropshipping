<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\StorefrontCampaign;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds the live SIMBAZU iPhone 17 Pro Max Lucky Draw campaign.
 *
 * Idempotent: skips creation when a campaign with the same slug already exists.
 * Run with: php artisan db:seed --class=LuckyDrawCampaignSeeder
 */
class LuckyDrawCampaignSeeder extends Seeder
{
    private const SLUG = 'iphone-17-pro-max-giveaway';

    public function run(): void
    {
        if (StorefrontCampaign::where('slug', self::SLUG)->exists()) {
            $this->command?->warn("Campaign with slug '".self::SLUG."' already exists — skipping.");

            return;
        }

        $tz = 'Africa/Abidjan';

        $campaign = StorefrontCampaign::create([
            'name' => 'iPhone 17 Pro Max Giveaway',
            'slug' => self::SLUG,
            'type' => 'lucky_draw',
            'status' => 'active',
            'is_active' => true,
            'starts_at' => Carbon::parse('2026-08-03 00:00:00', $tz),
            'ends_at' => Carbon::parse('2026-08-30 23:59:59', $tz),
            'timezone' => $tz,
            'priority' => 300,
            'stacking_mode' => 'stackable',
            'placements' => ['home_hero', 'home_strip'],
            'locale_visibility' => ['en', 'fr'],
            'hero_kicker' => 'Win an iPhone 17 Pro Max',
            'hero_subtitle' => 'Spend 30,000 FCFA or more in one order and you\'re automatically in the draw. 10 runners-up win a $20 gift card, and every participant gets 10% off.',
            'content' => '<p>Every qualifying order enters you into the draw automatically. The more you shop, the more chances you have to win.</p>',
            'locale_overrides' => [
                [
                    'locale' => 'fr',
                    'name' => 'Concours iPhone 17 Pro Max',
                    'hero_kicker' => 'Gagnez un iPhone 17 Pro Max',
                    'hero_subtitle' => 'Commandez 30 000 FCFA ou plus en une seule fois et participez automatiquement au tirage au sort. 10 finalistes gagnent une carte cadeau de 20 $, et chaque participant reçoit -10%.',
                    'content' => '<p>Chaque commande éligible vous inscrit automatiquement au tirage au sort. Plus vous commandez, plus vous avez de chances de gagner.</p>',
                    'landing_content' => '<p>Aucun code, aucun formulaire d\'inscription. Passez une commande éligible entre le 3 et le 30 août 2026 et vous êtes automatiquement inscrit. Un grand gagnant repart avec un iPhone 17 Pro Max, 10 finalistes gagnent chacun une carte cadeau SIMBAZU de 20 $, et chaque participant reçoit un bon de réduction de 10%.</p>',
                    'cta' => 'Voir les produits éligibles',
                    'terms' => '<ul><li>Le tirage au sort est ouvert à tout client SIMBAZU ayant passé une commande éligible payée (30 000 FCFA ou plus) entre le 3 et le 30 août 2026.</li><li>Chaque client est inscrit une fois par commande éligible.</li><li>Le tirage au sort a lieu le 31 août 2026 et les gagnants sont annoncés sur notre site et nos réseaux sociaux.</li><li>Les gagnants sont contactés par email et par téléphone dans les 48 heures suivant le tirage.</li><li>Les récompenses sont non transférables et sans valeur monétaire.</li></ul>',
                    'faq' => [
                        ['q' => 'Comment participer au tirage au sort ?', 'a' => 'Passez une commande de 30 000 FCFA ou plus sur Simbazu pour être automatiquement inscrit. Aucune inscription supplémentaire nécessaire.'],
                        ['q' => 'Quand aura lieu le tirage au sort ?', 'a' => 'Le tirage au sort aura lieu le 31 août 2026. Les gagnants seront annoncés sur notre site et nos réseaux sociaux.'],
                        ['q' => 'Puis-je participer plusieurs fois ?', 'a' => 'Oui, chaque commande éligible vous donne une entrée supplémentaire. Plus vous commandez, plus vous avez de chances de gagner.'],
                        ['q' => 'Les gagnants seront-ils contactés ?', 'a' => 'Oui, les gagnants seront contactés par email et par téléphone dans les 48 heures suivant le tirage au sort.'],
                        ['q' => 'Y a-t-il des frais cachés ?', 'a' => 'Non, la participation est automatique et gratuite pour toute commande éligible.'],
                    ],
                    'seo' => [
                        'title' => 'Gagnez un iPhone 17 Pro Max | Tirage au sort SIMBAZU',
                        'description' => 'Commandez 30 000 FCFA ou plus sur SIMBAZU et participez automatiquement au tirage au sort pour gagner un iPhone 17 Pro Max. 10 finalistes gagnent des cartes cadeaux. 31 août 2026.',
                    ],
                ],
            ],
            'lucky_draw_config' => [
                'min_order_amount' => 30000,
                'currency' => 'XOF',
                'max_participants' => 50,
                'grand_prize' => 'iPhone 17 Pro Max',
                'runner_up_count' => 10,
                'gift_card_amount' => 20,
                'gift_card_currency' => 'USD',
                'guaranteed_reward_type' => 'coupon_code',
                'guaranteed_reward_value' => 10,
                'show_remaining_spots' => true,
                'countdown_enabled' => true,
                'winner_announcement_at' => Carbon::parse('2026-08-31 12:00:00', $tz),
                'landing_content' => '<p>No codes, no registration forms. Place one qualifying order between 3 August and 30 August 2026 and you\'re automatically entered. One grand-prize winner takes home an iPhone 17 Pro Max, 10 runners-up each win a $20 SIMBAZU gift card, and every participant receives a 10% discount coupon.</p>',
                'cta' => 'Shop qualifying products now',
                'terms' => '<ul><li>The draw is open to every SIMBAZU customer with a qualifying paid order (30,000 FCFA or more) placed between 3 August and 30 August 2026.</li><li>Each customer is entered once per qualifying order.</li><li>The draw takes place on 31 August 2026 and winners are announced on our website and social media.</li><li>Winners are contacted by email and phone within 48 hours of the draw.</li><li>Rewards are non-transferable and have no cash value.</li></ul>',
                'faq' => [
                    ['q' => 'How do I enter the draw?', 'a' => 'Place an order of 30,000 FCFA or more on Simbazu to be automatically entered. No registration required.'],
                    ['q' => 'When will the draw take place?', 'a' => 'The draw will take place on 31 August 2026. Winners will be announced on our website and social media.'],
                    ['q' => 'Can I enter multiple times?', 'a' => 'Yes, each qualifying order gives you an additional entry. The more you shop, the better your chances.'],
                    ['q' => 'Will winners be notified?', 'a' => 'Yes, winners will be contacted by email and phone within 48 hours of the draw.'],
                    ['q' => 'Are there any hidden fees?', 'a' => 'No, participation is automatic and free with any qualifying order.'],
                ],
                'seo' => [
                    'title' => 'Win an iPhone 17 Pro Max | SIMBAZU Lucky Draw',
                    'description' => 'Spend 30,000 FCFA or more on SIMBAZU and automatically enter the draw to win an iPhone 17 Pro Max. 10 runners-up win gift cards. 31 August 2026.',
                ],
            ],
        ]);

        $this->command?->info('Created Lucky Draw campaign: '.$campaign->name);
        $this->command?->warn('Remember to set CAMPAIGN_LUCKY_DRAW_ENABLED=true in production .env to activate participation.');
    }
}
