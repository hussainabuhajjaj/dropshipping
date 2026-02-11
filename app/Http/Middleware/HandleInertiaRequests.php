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
use Illuminate\Support\Facades\Schema;
use App\Models\StorefrontBanner;
use Illuminate\Support\Facades\Storage;

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
        $authUser = $customer ?? $request->user();
        $cart = Cart::GetCustomerOrGuestCart();
//        $cart = Cart::query()->where('user_id', auth('web')->id())
//            ->orWhere('session_id', session()->id())
//            ->with('items')
//            ->first();

        $cart_items = isset($cart) ? $cart->items : collect([]);
        $cart_quantities = isset($cart) ? $cart->items->sum('quantity') : 0;
        $cart_items = (CartResource::collection($cart_items))->jsonSerialize();
        $cartSubtotal = isset($cart) ? $cart->subTotal() : 0;
        // Legacy session-cart logic kept for reference only.
        // The canonical cart source is the Cart model + CartResource above.
        // If you reintroduce session carts, update both totals + line serialization.
//        $cart = collect($request->session()->get('cart', []));
//        $cartSubtotal = $cart->reduce(function ($carry, $line) {
//            return $carry + ((float) ($line['price'] ?? 0) * (int) ($line['quantity'] ?? 0));
//        }, 0.0);
        $unreadNotifications = $authUser && method_exists($authUser, 'unreadNotifications')
            ? $authUser->unreadNotifications()->count()
            : 0;
        $site = Schema::hasTable('site_settings')
            ? SiteSetting::query()->first()
            : null;
        $locale = app()->getLocale();
        $storefront = Schema::hasTable('storefront_settings')
            ? StorefrontSetting::latestForLocale($locale)
            : null;
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
                'user' => $authUser,
            ],
            'flash' => [
                'cart_notice' => $request->session()->get('cart_notice'),
                'review_notice' => $request->session()->get('review_notice'),
                'return_notice' => $request->session()->get('return_notice'),
                'wishlist_notice' => $request->session()->get('wishlist_notice'),
                'contact_notice' => $request->session()->get('contact_notice'),
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
            'notifications' => [
                'unreadCount' => $unreadNotifications,
            ],
            'unreadCount' => $unreadNotifications,
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
                'title' => $site?->localizedValue('meta_title', $locale) ?? $site?->meta_title ?? $site?->site_name ?? config('app.name', 'Simbazu'),
                'description' => $site?->localizedValue('meta_description', $locale) ?? $site?->meta_description ?? $site?->site_description ?? null,
                'image' => $site?->logo_path ?? null,
            ],
            'categories' => $this->rootCategoriesTree(['children', 'children.children']),
            'homepagePromotions' => app(PromotionHomepageService::class)->getHomepagePromotions(),
            'popupBanners' => $this->popupBanners(),
        ];
    }

    private function popupBanners(): array
    {
        $locale = app()->getLocale();

        $banners = StorefrontBanner::active()
            ->byDisplayType('popup')
            ->with(['product.images', 'category'])
            ->orderBy('display_order')
            ->get();

        return $banners->map(function (StorefrontBanner $banner) use ($locale) {
            return [
                'id' => $banner->id,
                'title' => $banner->localizedValue('title', $locale),
                'description' => $banner->localizedValue('description', $locale),
                'imagePath' => $this->resolveBannerImage($banner),
                'badgeText' => $banner->localizedValue('badge_text', $locale),
                'ctaText' => $banner->localizedValue('cta_text', $locale),
                'ctaUrl' => $banner->getCtaUrl(),
                'ends_at' => $banner->ends_at,
                'promotion' => null,
            ];
        })->values()->all();
    }

    private function resolveBannerImage(StorefrontBanner $banner): ?string
    {
        if ($banner->image_path) {
            return $this->resolveImagePath($banner->image_path);
        }

        if ($banner->target_type === 'product' && $banner->product) {
            $image = $banner->product->images?->first()?->url ?? null;
            return $this->resolveImagePath($image);
        }

        if ($banner->target_type === 'category' && $banner->category) {
            return $this->resolveImagePath($banner->category->hero_image ?? null);
        }

        return null;
    }

    private function resolveImagePath(?string $path): ?string
    {
        // NOTE: Image URL normalization is duplicated in HomeController + API controller.
        // Keep in sync if URL handling changes.
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url(Storage::url($path));
    }
}
