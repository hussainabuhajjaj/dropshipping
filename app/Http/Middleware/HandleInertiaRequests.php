<?php

namespace App\Http\Middleware;

use App\Http\Resources\User\CartResource;
use App\Models\Cart;
use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Http\Controllers\Storefront\Concerns\FormatsCategories;
use App\Models\SiteSetting;
use App\Models\StorefrontSetting;
use App\Services\Promotions\PromotionHomepageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class HandleInertiaRequests extends Middleware
{
    use FormatsCategories;

    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $customer = $request->user('customer');
        $cart = Cart::query()->where('user_id', auth('web')->id())
            ->orWhere('session_id', session()->id())
            ->with('items')
            ->first();

        $cart_items = isset($cart) ? $cart->items : collect([]);
        $cart_quantities = isset($cart) ? $cart->items->sum('quantity') : 0;
        $cart_items = (CartResource::collection($cart_items))->jsonSerialize();
        $cartSubtotal = isset($cart) ? $cart->subTotal() : 0;
        //        $cart = collect($request->session()->get('cart', []));
//        $cartSubtotal = $cart->reduce(function ($carry, $line) {
//            return $carry + ((float) ($line['price'] ?? 0) * (int) ($line['quantity'] ?? 0));
//        }, 0.0);
        $site = Schema::hasTable('site_settings')
            ? SiteSetting::query()->first()
            : null;
        $storefront = Schema::hasTable('storefront_settings')
            ? StorefrontSetting::query()->latest()->first()
            : null;
        $locale = app()->getLocale();
        $translations = [];
        $translationsPath = resource_path("lang/{$locale}.json");
        if (is_file($translationsPath)) {
            $decoded = json_decode(file_get_contents($translationsPath), true);
            if (is_array($decoded)) {
                $translations = $decoded;
            }
        }
//dd(parent::share($request));
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $customer ?? $request->user(),
            ],
            'flash' => [
                'cart_notice' => $request->session()->get('cart_notice'),
                'review_notice' => $request->session()->get('review_notice'),
                'return_notice' => $request->session()->get('return_notice'),
                'wishlist_notice' => $request->session()->get('wishlist_notice'),
                'status' => $request->session()->get('status'),
            ],
            'cart' => [
                'lines' => $cart_items,
                'count' => $cart_quantities,
                'subtotal' => $cartSubtotal,
            ],
            'wishlist' => [
                'count' => count($request->session()->get('wishlist', [])),
            ],
            'site' => $site,
            'storefront' => $storefront,
            'appUrl' => rtrim(config('app.url'), '/'),
            'locale' => $locale,
            'availableLocales' => [
                'en' => 'English',
                'fr' => 'Français',
            ],
            'translations' => $translations,
            'seo' => [
                'title' => $site?->meta_title ?? $site?->site_name ?? config('app.name', 'Simbazu'),
                'description' => $site?->meta_description ?? $site?->site_description ?? null,
                'image' => $site?->logo_path ?? null,
            ],
            'categories' => $this->rootCategoriesTree(['children', 'children.children']),
            'homepagePromotions' => Cache::remember(
                'storefront.homepagePromotions',
                now()->addMinutes(5),
                fn () => app(PromotionHomepageService::class)->getHomepagePromotions()
            ),
        ];
    }
}
