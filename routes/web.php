<?php

// --- Core Laravel & Vendor Imports ---
use App\Http\Controllers\KorapayController;
use App\Http\Controllers\Storefront\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// --- Storefront Controllers ---
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\ExpressCheckoutController;
use App\Http\Controllers\Storefront\TrackingPageController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\WishlistController;
use App\Http\Controllers\Storefront\UserPreferenceController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\ProductReviewController;
use App\Http\Controllers\Storefront\ReviewHelpfulController;
use App\Http\Controllers\Storefront\ReturnRequestController;
use App\Http\Controllers\Storefront\ReturnLabelController;
use App\Http\Controllers\Storefront\AffiliateController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\PromotionController;
use App\Http\Controllers\Storefront\NewsletterController;
use App\Http\Controllers\Storefront\NewsletterTrackingController;
use App\Http\Controllers\Storefront\SupportChatController;
use App\Http\Controllers\Storefront\MetaCatalogFeedController;
use App\Http\Controllers\Api\WhatsAppOrderIntentController;

// --- Webhook Controllers ---
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Http\Controllers\Webhooks\TrackingWebhookController;
use App\Http\Controllers\Webhooks\CJWebhookController;
use App\Http\Controllers\Webhooks\CJDropshippingController;

// --- Admin & Misc Controllers ---
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\AdminPasswordResetLinkController;
use App\Http\Controllers\Admin\AdminNewPasswordController;
use App\Http\Controllers\Payments\PaystackCallbackController;
use App\Http\Controllers\Seo\SitemapController;
use App\Http\Controllers\Seo\RobotsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AliExpressOAuthController;
use App\Http\Controllers\ShortUrlController;

// --- Middleware ---
use App\Http\Middleware\VerifyPaymentWebhookSignature;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\VerifyTrackingWebhookSignature;

// ---------------- ROUTES ----------------

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/locale/{locale}', function (string $locale, Request $request) {
    $locale = strtolower($locale);
    $available = ['en', 'fr'];

    if (!in_array($locale, $available, true)) {
        $locale = config('app.locale', 'en');
    }

    $request->session()->put('locale', $locale);

    $redirectTo = url()->previous() ?: url('/');

    return redirect()->to($redirectTo)->withCookie(cookie('locale', $locale, 60 * 24 * 365));
})->name('locale.set');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/feeds/meta-catalog.csv', MetaCatalogFeedController::class)->name('feeds.meta-catalog');
Route::post('/api/whatsapp-intents', [WhatsAppOrderIntentController::class, 'store'])->name('api.whatsapp-intents.store');
Route::get('/api/whatsapp-intents/{reference}', [WhatsAppOrderIntentController::class, 'show'])->name('api.whatsapp-intents.show');
Route::middleware('auth:admin')->group(function () {
    Route::post('/api/whatsapp-intents/{reference}/convert', [WhatsAppOrderIntentController::class, 'convert'])->name('api.whatsapp-intents.convert');
    Route::post('/api/whatsapp-intents/{reference}/expire', [WhatsAppOrderIntentController::class, 'expire'])->name('api.whatsapp-intents.expire');
});

// Short URL routes
Route::get('/s/{code}', [ShortUrlController::class, 'redirect'])->name('short-url.redirect')->where('code', '.*');
Route::post('/api/short-url', [ShortUrlController::class, 'create'])->name('short-url.create');
Route::get('/api/short-url/product/{product}', [ShortUrlController::class, 'forProduct'])->name('short-url.product');
Route::get('/.well-known/apple-app-site-association', function () {
    $teamId = (string) config('mobile_app_links.ios.team_id', '');
    $bundleId = (string) config('mobile_app_links.ios.bundle_id', '');
    $paths = config('mobile_app_links.ios.paths', ['/products/*']);

    $appId = trim($teamId) !== '' && trim($bundleId) !== ''
        ? sprintf('%s.%s', trim($teamId), trim($bundleId))
        : null;

    return response()->json([
        'applinks' => [
            'apps' => [],
            'details' => $appId
                ? [[
                    'appIDs' => [$appId],
                    'components' => collect($paths)
                        ->filter(fn ($path) => is_string($path) && $path !== '')
                        ->map(fn (string $path) => ['/' => $path])
                        ->values()
                        ->all(),
                ]]
                : [],
        ],
    ])->header('Content-Type', 'application/json');
});
Route::get('/.well-known/assetlinks.json', function () {
    $packageName = (string) config('mobile_app_links.android.package_name', '');
    $fingerprints = collect(config('mobile_app_links.android.sha256_cert_fingerprints', []))
        ->filter(fn ($value) => is_string($value) && trim($value) !== '')
        ->map(fn (string $value) => trim($value))
        ->values()
        ->all();

    if ($packageName === '' || empty($fingerprints)) {
        return response()->json([]);
    }

    return response()->json([
        [
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => $packageName,
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ],
    ])->header('Content-Type', 'application/json');
});
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('category.show');
Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/collections', [\App\Http\Controllers\Storefront\CollectionController::class, 'index'])->name('collections.index');
Route::get('/collections/{slug}', [\App\Http\Controllers\Storefront\CollectionController::class, 'show'])->name('collections.show');
Route::get('/campaigns/{campaign:slug}', [\App\Http\Controllers\Storefront\CampaignController::class, 'show'])->name('campaigns.show');
Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');
Route::get('/search', SearchController::class)->name('search');
Route::get('/search/popular', [SearchController::class, 'getPopularSearches'])->name('search.popular');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::delete('/cart/{lineId}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::patch('/cart/{lineId}', [CartController::class, 'update'])->name('cart.update');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

Route::post('/cart/abandon', [CartController::class, 'abandon'])->name('cart.abandon');

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::post('/checkout/apply-gift-card', [CheckoutController::class, 'applyGiftCard'])->name('checkout.apply-gift-card');
Route::post('/checkout/remove-gift-card', [CheckoutController::class, 'removeGiftCard'])->name('checkout.remove-gift-card');
Route::post('/express-checkout/payment-intent', [ExpressCheckoutController::class, 'createPaymentIntent'])->name('express-checkout.payment-intent');
Route::post('/express-checkout/complete', [ExpressCheckoutController::class, 'complete'])->name('express-checkout.complete');
Route::get('/payments/paystack/callback', PaystackCallbackController::class)->name('payments.paystack.callback');
Route::get('/orders/confirmation/{number}', [CheckoutController::class, 'confirmation'])->name('orders.confirmation');
Route::get('/orders/track', TrackingPageController::class)->name('orders.track');
Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index');
Route::get('/promotions/flash-sales', [PromotionController::class, 'flashSales'])->name('promotions.flash-sales');
Route::get('/promotions/deals', [PromotionController::class, 'deals'])->name('promotions.deals');
Route::get('/promotions/products', [PromotionController::class, 'promotedProducts'])->name('promotions.products');
Route::get('/promotions/categories', [PromotionController::class, 'promotedCategories'])->name('promotions.categories');
Route::post('/newsletter/subscribe', [NewsletterController::class, 'store'])->name('newsletter.subscribe');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
Route::get('/newsletter/track/open/{token}', [NewsletterTrackingController::class, 'open'])->name('newsletter.track.open');
Route::get('/newsletter/track/click/{token}', [NewsletterTrackingController::class, 'click'])->name('newsletter.track.click');

Route::get('/media/proxy', function () {
    $url = (string)request()->query('url', '');
    if ($url === '') {
        abort(404);
    }

    if (!str_starts_with($url, 'https://cf.cjdropshipping.com/')) {
        abort(403);
    }

    $response = Http::timeout(10)
        ->withHeaders([
            'User-Agent' => 'Mozilla/5.0',
            'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
        ])
        ->get($url);

    if (!$response->successful()) {
        abort(404);
    }

    return response($response->body(), 200)
        ->header('Content-Type', $response->header('Content-Type') ?? 'image/jpeg')
        ->header('Cache-Control', 'public, max-age=86400');
});

Route::get('/coming-soon', function () {
    return Inertia::render('ComingSoon');
})->name('coming-soon');


Route::get('/login-developer', function (Request $request) {
    $expected = (string) config('app.developer_bypass_token', '');
    if ($expected === '') {
        abort(404);
    }

    $provided = (string) $request->query('token', '');
    if ($provided === '' || ! hash_equals($expected, $provided)) {
        abort(404);
    }

    $ttlHours = (int) config('app.developer_bypass_ttl_hours', 12);

    $request->session()->regenerate();
    $request->session()->put('is_developer', true);
    $request->session()->put('is_developer_expires_at', now()->addHours($ttlHours)->timestamp);

    return redirect('/');
})->name('developer.login');

Route::get('/logout-developer', function (Request $request) {
    $request->session()->forget(['is_developer', 'is_developer_expires_at']);

    return redirect('/');
})->name('developer.logout');


Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)
    ->middleware(['throttle:30,1', VerifyPaymentWebhookSignature::class, IdempotencyMiddleware::class])
    ->name('webhooks.payments');

Route::post('/webhooks/paystack', [App\Http\Controllers\Webhooks\PaystackWebhookController::class, '__invoke'])
    ->middleware('throttle:30,1')
    ->name('webhooks.paystack');

Route::post('/webhooks/tracking/{provider}', TrackingWebhookController::class)
    ->middleware(['throttle:30,1', VerifyTrackingWebhookSignature::class])
    ->name('webhooks.tracking');

Route::post('/webhooks/cj', CJWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('webhooks.cj');

Route::post('/webhooks/cj/order-status', CJDropshippingController::class)
    ->middleware(['throttle:30,1'])
    ->name('webhooks.cj.status');

Route::prefix(config('filament.path', 'admin'))
    ->middleware('guest:admin')
    ->group(function () {
        Route::get('/forgot-password', [AdminPasswordResetLinkController::class, 'create'])->name('admin.password.request');
        Route::post('/forgot-password', [AdminPasswordResetLinkController::class, 'store'])->name('admin.password.email');
        Route::get('/reset-password/{token}', [AdminNewPasswordController::class, 'create'])->name('admin.password.reset');
        Route::post('/reset-password', [AdminNewPasswordController::class, 'store'])->name('admin.password.store');
    });

Route::middleware('auth:admin')
    ->prefix(config('filament.path', 'admin') . '/exports')
    ->name('admin.exports.')
    ->group(function () {
        Route::get('/products', [ExportController::class, 'products'])->name('products');
        Route::get('/customers', [ExportController::class, 'customers'])->name('customers');
        Route::get('/products/{product}/images', [ExportController::class, 'productImages'])
            ->middleware('signed')
            ->name('product-images');
    });

Route::get('/legal/shipping-policy', [PageController::class, 'shippingPolicy'])->name('legal.shipping');
Route::get('/legal/refund-policy', [PageController::class, 'refundPolicy'])->name('legal.refund');
Route::get('/legal/privacy-policy', [PageController::class, 'privacyPolicy'])->name('legal.privacy');
Route::get('/legal/cookie-policy', [PageController::class, 'cookiePolicy'])->name('legal.cookies');
Route::get('/legal/terms-of-service', [PageController::class, 'termsOfService'])->name('legal.terms');
Route::get('/legal/customs-disclaimer', [PageController::class, 'customsDisclaimer'])->name('legal.customs');
Route::get('/legal/user-data-deletion', [PageController::class, 'userDataDeletion'])->name('legal.data-deletion');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'submitContact'])->name('contact.submit');
Route::inertia('/support', 'Support/Index')->name('support');
Route::inertia('/faq', 'Faq/Index')->name('faq');

Route::middleware(['auth:customer', 'throttle:60,1'])
    ->prefix('/support/chat')
    ->name('support.chat.')
    ->group(function () {
        Route::post('/start', [SupportChatController::class, 'start'])->name('start');
        Route::post('/respond', [SupportChatController::class, 'respond'])->name('respond');
        Route::post('/forward', [SupportChatController::class, 'forward'])->name('forward');
        Route::post('/attachment', [SupportChatController::class, 'attachment'])->name('attachment');
        Route::get('/messages', [SupportChatController::class, 'messages'])->name('messages');
    });

// Redirect legacy /policies/* routes to /legal/* for backward compatibility
Route::redirect('/policies/shipping', '/legal/shipping-policy', 301);
Route::redirect('/policies/refund', '/legal/refund-policy', 301);
Route::redirect('/policies/terms', '/legal/terms-of-service', 301);
Route::redirect('/policies/privacy', '/legal/privacy-policy', 301);
Route::redirect('/policies/cookies', '/legal/cookie-policy', 301);
Route::redirect('/policies/data-deletion', '/legal/user-data-deletion', 301);
Route::redirect('/policies/about', '/about', 301);

Route::middleware(['auth:customer', 'verified'])->group(function () {
    Route::get('/account', [AccountController::class, 'index'])->name('account.index');
    Route::get('/account/addresses', [AccountController::class, 'addresses'])->name('account.addresses');
    Route::get('/account/orders', [AccountController::class, 'orders'])->name('account.orders');
    Route::get('/account/payments', [AccountController::class, 'payments'])->name('account.payments');
    Route::get('/account/refunds', [AccountController::class, 'refunds'])->name('account.refunds');
    Route::get('/account/wallet', [AccountController::class, 'wallet'])->name('account.wallet');
    Route::get('/account/notifications', [AccountController::class, 'notifications'])->name('account.notifications');
    Route::post('/account/notifications/{notification}/read', [AccountController::class, 'markNotificationRead'])
        ->name('account.notifications.read');
    Route::post('/account/notifications/read-all', [AccountController::class, 'markAllNotificationsRead'])
        ->name('account.notifications.read-all');
    Route::post('/account/addresses', [AccountController::class, 'storeAddress'])->name('account.addresses.store');
    Route::put('/account/addresses/{address}', [AccountController::class, 'updateAddress'])->name('account.addresses.update');
    Route::delete('/account/addresses/{address}', [AccountController::class, 'destroyAddress'])->name('account.addresses.destroy');
    Route::post('/account/payment-methods', [AccountController::class, 'storePaymentMethod'])->name('account.payment-methods.store');
    Route::delete('/account/payment-methods/{paymentMethod}', [AccountController::class, 'destroyPaymentMethod'])->name('account.payment-methods.destroy');
    Route::post('/account/gift-cards/redeem', [AccountController::class, 'redeemGiftCard'])->name('account.gift-cards.redeem');
    Route::post('/account/coupons/save', [AccountController::class, 'saveCoupon'])->name('account.coupons.save');
    Route::delete('/account/coupons/{couponRedemption}', [AccountController::class, 'destroyCoupon'])->name('account.coupons.destroy');
    Route::post('/account/claim-orders', [AccountController::class, 'claimOrders'])->name('account.claim-orders');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/products/{product:slug}/reviews', [ProductReviewController::class, 'store'])
        ->name('products.reviews.store');
    Route::post('/reviews/{review}/helpful', [ReviewHelpfulController::class, 'vote'])
        ->name('reviews.helpful');
    Route::post('/returns', [ReturnRequestController::class, 'store'])->name('returns.store');
    Route::get('/returns/{returnRequest}/label/download', [ReturnLabelController::class, 'download'])->name('returns.label.download');
    Route::get('/returns/{returnRequest}/label/preview', [ReturnLabelController::class, 'preview'])->name('returns.label.preview');
    Route::get('/account/wishlist', [WishlistController::class, 'index'])->name('account.wishlist');
    Route::post('/account/wishlist', [WishlistController::class, 'store'])->name('account.wishlist.store');
    Route::delete('/account/wishlist/{product}', [WishlistController::class, 'destroy'])->name('account.wishlist.destroy');
});

Route::get('/aliexpress/oauth/redirect', [AliExpressOAuthController::class, 'redirect'])->name('aliexpress.oauth.redirect');
Route::get('/aliexpress/oauth/callback', [AliExpressOAuthController::class, 'callback'])->name('aliexpress.oauth.callback');
Route::post('/aliexpress/oauth/refresh', [AliExpressOAuthController::class, 'refresh'])->name('aliexpress.oauth.refresh');

Route::prefix('pay/{type}')->name('pay.')->group(function () {
    Route::get('/', [PaymentController::class, 'index'])->name('index');
    Route::post('/checkout', [PaymentController::class, 'checkout'])->name('checkout');
    Route::get('/redirect', [PaymentController::class, 'redirect'])->name('redirect');
    Route::get('/{id}', [PaymentController::class, 'index'])->whereNumber('id')->name('index.with-id');
    Route::post('/{id}/checkout', [PaymentController::class, 'checkout'])->whereNumber('id')->name('checkout.with-id');
    Route::get('/{id}/redirect', [PaymentController::class, 'redirect'])->whereNumber('id')->name('redirect.with-id');
});

// Paystack Routes
Route::middleware(['throttle:30,1'])->prefix('paystack')->name('paystack.')->group(function () {
    Route::post('/initialize', [App\Http\Controllers\PaystackController::class, 'initialize'])->name('initialize');
    Route::post('/verify', [App\Http\Controllers\PaystackController::class, 'verify'])->name('verify');
    Route::post('/mobile-money/charge', [App\Http\Controllers\PaystackController::class, 'mobileMoneyCharge'])->name('mobile_money.charge');
});

// Paystack callback route (GET only - for user redirect after payment)
Route::get('/paystack/callback', [App\Http\Controllers\Payments\PaystackCallbackController::class, '__invoke'])
    ->name('paystack.callback');

// Paystack webhook route (POST only - for Paystack server notifications)
Route::post('/paystack/webhook', [App\Http\Controllers\Webhooks\PaystackWebhookController::class, '__invoke'])
    ->middleware('throttle:30,1')
    ->name('paystack.webhook');


// KoraPay routes (commented out - using Paystack instead)
/*
Route::post('/korapay/initialize', [KorapayController::class, 'initialize'])->name('korapay.initialize');
Route::post('/korapay/webhook', [KorapayController::class, 'webhook'])->name('korapay.webhook');
Route::get('/korapay/verify/{reference}', [KorapayController::class, 'verify'])->name('korapay.verify');
*/

// Currency & Language routes (legacy - redirect to new API)
Route::post('/currency', [UserPreferenceController::class, 'updateCurrency'])->name('currency.set');
Route::post('/language', [UserPreferenceController::class, 'updateLanguage'])->name('language.set');
Route::post('/preferences', [App\Http\Controllers\Storefront\SessionController::class, 'setPreferences'])->name('preferences.set');
Route::get('/api/preferences', [App\Http\Controllers\Storefront\SessionController::class, 'getPreferences'])->name('preferences.get');
Route::get('aa', function () {
    return view('payment');
});

// QR Campaign / Reward Claim routes
Route::get('/r/{slug}', [App\Http\Controllers\Storefront\ClaimController::class, 'show'])->name('rewards.claim');
Route::post('/r/{slug}/claim', [App\Http\Controllers\Storefront\ClaimController::class, 'claim'])
    ->middleware('auth:customer')
    ->name('rewards.claim.submit');

Route::get('/download', [App\Http\Controllers\Storefront\DownloadController::class, 'index'])->name('download');
Route::get('/download/apk', [App\Http\Controllers\Storefront\DownloadController::class, 'downloadApk'])->name('download.apk');
Route::get('/api/app/latest', [App\Http\Controllers\Storefront\DownloadController::class, 'latestApkInfo'])->name('api.app.latest');

// Affiliate routes
Route::middleware('guest')->group(function () {
    Route::get('/affiliate/signup', [AffiliateController::class, 'signupForm'])->name('affiliate.signup');
    Route::post('/affiliate/signup', [AffiliateController::class, 'signup'])->name('affiliate.signup.store');
});

Route::middleware('auth:affiliate')->prefix('affiliate')->name('affiliate.')->group(function () {
    Route::get('/', [AffiliateController::class, 'dashboard'])->name('dashboard');
    Route::post('/withdrawal', [AffiliateController::class, 'requestWithdrawal'])->name('withdrawal.request');
});

require __DIR__ . '/auth.php';
