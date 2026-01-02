<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Http\Controllers\Webhooks\CJDropshippingController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\ExpressCheckoutController;
use App\Http\Controllers\Storefront\TrackingPageController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\WishlistController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\ProductReviewController;
use App\Http\Controllers\Storefront\ReviewHelpfulController;
use App\Http\Controllers\Storefront\ReturnRequestController;
use App\Http\Controllers\Storefront\ReturnLabelController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Webhooks\CJWebhookController;
use App\Http\Controllers\Webhooks\TrackingWebhookController;
use App\Http\Controllers\Payments\PaystackCallbackController;
use App\Http\Controllers\Seo\SitemapController;
use App\Http\Controllers\Seo\RobotsController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\AdminPasswordResetLinkController;
use App\Http\Controllers\Admin\AdminNewPasswordController;
use App\Http\Middleware\VerifyPaymentWebhookSignature;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\VerifyTrackingWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\AliExpressOAuthController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/locale/{locale}', function (string $locale, Request $request) {
    $locale = strtolower($locale);
    $available = ['en', 'fr'];

    if (! in_array($locale, $available, true)) {
        $locale = config('app.locale', 'en');
    }

    $request->session()->put('locale', $locale);

    $redirectTo = url()->previous() ?: url('/');

    return redirect()->to($redirectTo)->withCookie(cookie('locale', $locale, 60 * 24 * 365));
})->name('locale.set');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/search', SearchController::class)->name('search');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::delete('/cart/{lineId}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::patch('/cart/{lineId}', [CartController::class, 'update'])->name('cart.update');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::post('/express-checkout/payment-intent', [ExpressCheckoutController::class, 'createPaymentIntent'])->name('express-checkout.payment-intent');
Route::post('/express-checkout/complete', [ExpressCheckoutController::class, 'complete'])->name('express-checkout.complete');
Route::get('/payments/paystack/callback', PaystackCallbackController::class)->name('payments.paystack.callback');
Route::get('/orders/confirmation/{number}', [CheckoutController::class, 'confirmation'])->name('orders.confirmation');
Route::get('/orders/track', TrackingPageController::class)->name('orders.track');

Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)
    ->middleware(['throttle:30,1', VerifyPaymentWebhookSignature::class, IdempotencyMiddleware::class])
    ->name('webhooks.payments');

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
    });

Route::get('/legal/shipping-policy', [PageController::class, 'shippingPolicy'])->name('legal.shipping');
Route::get('/legal/refund-policy', [PageController::class, 'refundPolicy'])->name('legal.refund');
Route::get('/legal/privacy-policy', [PageController::class, 'privacyPolicy'])->name('legal.privacy');
Route::get('/legal/terms-of-service', [PageController::class, 'termsOfService'])->name('legal.terms');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::inertia('/support', 'Support/Index')->name('support');
Route::inertia('/faq', 'Faq/Index')->name('faq');

// Redirect legacy /policies/* routes to /legal/* for backward compatibility
Route::redirect('/policies/shipping', '/legal/shipping-policy', 301);
Route::redirect('/policies/refund', '/legal/refund-policy', 301);
Route::redirect('/policies/terms', '/legal/terms-of-service', 301);
Route::redirect('/policies/privacy', '/legal/privacy-policy', 301);
Route::redirect('/policies/about', '/about', 301);

Route::middleware('auth:customer')->group(function () {
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

require __DIR__.'/auth.php';
