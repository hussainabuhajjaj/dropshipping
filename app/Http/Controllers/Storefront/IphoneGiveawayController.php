<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Inertia\Inertia;

class IphoneGiveawayController extends Controller
{
    public function index()
    {
        $locale = app()->getLocale();

        $products = Product::query()
            ->where('is_active', true)
            ->where('status', 'published')
            ->where('selling_price', '>=', 30000)
            ->whereHas('variants')
            ->with(['images', 'variants', 'category'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->inRandomOrder()
            ->take(8)
            ->get()
            ->map(function (Product $product) {
                $price = $product->variants->min('price') ?? $product->selling_price;
                $compareAt = $product->variants->max('compare_at_price') ?? $product->compare_at_price;

                return [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'image' => $product->images->first()?->url,
                    'price' => (float) $price,
                    'compare_at_price' => $compareAt ? (float) $compareAt : null,
                    'rating' => (float) ($product->reviews_avg_rating ?? 0),
                    'reviews_count' => (int) ($product->reviews_count ?? 0),
                    'currency' => $product->currency ?? 'XOF',
                ];
            });

        $faq = $locale === 'fr' ? [
            ['q' => 'Comment participer au tirage au sort ?', 'a' => 'Passez une commande de 30 000 FCFA ou plus sur Simbazu pour être automatiquement inscrit au tirage au sort. Aucune inscription supplémentaire nécessaire.'],
            ['q' => 'Quand aura lieu le tirage au sort ?', 'a' => 'Le tirage au sort aura lieu le 31 août 2026. Le gagnant sera annoncé sur notre site et nos réseaux sociaux.'],
            ['q' => 'Puis-je participer plusieurs fois ?', 'a' => 'Oui, chaque commande de 30 000 FCFA ou plus vous donne une entrée supplémentaire. Plus vous commandez, plus vous avez de chances de gagner.'],
            ['q' => 'Le gagnant sera-t-il contacté ?', 'a' => 'Oui, le gagnant sera contacté par email et par téléphone dans les 48 heures suivant le tirage au sort.'],
            ['q' => 'Y a-t-il des frais cachés ?', 'a' => 'Non, la participation est automatique et gratuite pour toute commande de 30 000 FCFA ou plus.'],
            ['q' => 'Puis-je choisir la couleur de l\'iPhone ?', 'a' => 'Le gagnant pourra choisir la couleur et la configuration de son iPhone 17.'],
        ] : [
            ['q' => 'How do I enter the giveaway?', 'a' => 'Place an order of 30,000 FCFA or more on Simbazu to be automatically entered into the draw. No additional registration required.'],
            ['q' => 'When will the draw take place?', 'a' => 'The draw will take place on August 31, 2026. The winner will be announced on our website and social media.'],
            ['q' => 'Can I enter multiple times?', 'a' => 'Yes, each order of 30,000 FCFA or more gives you an additional entry. The more you shop, the better your chances.'],
            ['q' => 'Will the winner be notified?', 'a' => 'Yes, the winner will be contacted by email and phone within 48 hours of the draw.'],
            ['q' => 'Are there any hidden fees?', 'a' => 'No, participation is automatic and free with any order of 30,000 FCFA or more.'],
            ['q' => 'Can I choose the iPhone color?', 'a' => 'The winner can choose the color and configuration of their iPhone 17.'],
        ];

        return Inertia::render('Campaigns/IphoneGiveaway', [
            'products' => $products,
            'faq' => $faq,
            'campaignEndsAt' => '2026-08-31T23:59:59+00:00',
            'minOrderAmount' => 30000,
            'winnerCount' => 1,
            'trustStats' => [
                ['icon' => 'package', 'label' => __('18,000+ Products'), 'desc' => __('From fashion to electronics')],
                ['icon' => 'shield', 'label' => __('Secure Payment'), 'desc' => __('Protected by Paystack')],
                ['icon' => 'truck', 'label' => __('Fast Delivery'), 'desc' => __('3-7 days to your door')],
                ['icon' => 'message-circle', 'label' => __('WhatsApp Ordering'), 'desc' => __('Order via chat')],
            ],
            'categories' => [
                ['name' => __('Fashion'), 'slug' => 'womens-clothing', 'image' => '/images/categories/fashion.jpg', 'color' => 'from-pink-500 to-rose-600'],
                ['name' => __('Electronics'), 'slug' => 'electronics', 'image' => '/images/categories/electronics.jpg', 'color' => 'from-blue-500 to-indigo-600'],
                ['name' => __('Beauty'), 'slug' => 'beauty', 'image' => '/images/categories/beauty.jpg', 'color' => 'from-purple-500 to-fuchsia-600'],
                ['name' => __('Home'), 'slug' => 'home', 'image' => '/images/categories/home.jpg', 'color' => 'from-emerald-500 to-teal-600'],
                ['name' => __('Accessories'), 'slug' => 'accessories', 'image' => '/images/categories/accessories.jpg', 'color' => 'from-amber-500 to-orange-600'],
            ],
            'features' => [
                ['icon' => 'shopping-bag', 'title' => __('18,000+ Products'), 'desc' => __('From top brands and trusted suppliers')],
                ['icon' => 'shield-check', 'title' => __('Secure Payments'), 'desc' => __('Pay via Paystack, mobile money, or bank transfer')],
                ['icon' => 'truck', 'title' => __('Fast Delivery'), 'desc' => __('3-7 business days across Côte d\'Ivoire')],
                ['icon' => 'headphones', 'title' => __('24/7 Support'), 'desc' => __('We\'re here to help, anytime')],
                ['icon' => 'rotate-ccw', 'title' => __('Easy Returns'), 'desc' => __('14-day return policy, no questions asked')],
                ['icon' => 'gift', 'title' => __('Free Giveaway Entry'), 'desc' => __('Every 30,000 FCFA+ order enters the draw')],
            ],
            'steps' => [
                ['step' => 1, 'icon' => 'shopping-cart', 'title' => __('Shop'), 'desc' => __('Spend 30,000 FCFA or more in one order')],
                ['step' => 2, 'icon' => 'check-circle', 'title' => __('Auto-Enter'), 'desc' => __('You\'re automatically entered — no codes needed')],
                ['step' => 3, 'icon' => 'calendar', 'title' => __('Wait'), 'desc' => __('Sit tight until the live draw on August 31')],
                ['step' => 4, 'icon' => 'award', 'title' => __('Win'), 'desc' => __('One lucky customer takes home an iPhone 17')],
            ],
        ]);
    }
}
