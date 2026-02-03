<?php

use App\Http\Controllers\Api\Storefront\CategoryController;
use App\Http\Controllers\Api\Storefront\HomeController;
use App\Http\Controllers\Api\Storefront\OrderController;
use App\Http\Controllers\Api\Storefront\AddressController as StorefrontAddressController;
use App\Http\Controllers\Api\Storefront\PaymentMethodController as StorefrontPaymentMethodController;
use App\Http\Controllers\Api\Storefront\ProductController;
use App\Http\Controllers\Api\Storefront\TrackingController;
use App\Http\Controllers\Api\Storefront\AuthController as StorefrontAuthController;
use App\Http\Controllers\Api\Storefront\AccountController as StorefrontAccountController;
use App\Http\Controllers\Api\Mobile\V1\AuthController as MobileAuthController;
use App\Http\Controllers\Api\Mobile\V1\HomeController as MobileHomeController;
use App\Http\Controllers\Api\Mobile\V1\CategoryController as MobileCategoryController;
use App\Http\Controllers\Api\Mobile\V1\ProductController as MobileProductController;
use App\Http\Controllers\Api\Mobile\V1\ProductReviewController as MobileProductReviewController;
use App\Http\Controllers\Api\Mobile\V1\CartController as MobileCartController;
use App\Http\Controllers\Api\Mobile\V1\CheckoutController as MobileCheckoutController;
use App\Http\Controllers\Api\Mobile\V1\OrderController as MobileOrderController;
use App\Http\Controllers\Api\Mobile\V1\PaymentController as MobilePaymentController;
use App\Http\Controllers\Api\Mobile\V1\AddressController as MobileAddressController;
use App\Http\Controllers\Api\Mobile\V1\PaymentMethodController as MobilePaymentMethodController;
use App\Http\Controllers\Api\Mobile\V1\WishlistController as MobileWishlistController;
use App\Http\Controllers\Api\Mobile\V1\NotificationController as MobileNotificationController;
use App\Http\Controllers\Api\Mobile\V1\NewsletterController as MobileNewsletterController;
use App\Http\Controllers\Api\Mobile\V1\RewardsController as MobileRewardsController;
use App\Http\Controllers\Api\Mobile\V1\WalletController as MobileWalletController;
use App\Http\Controllers\Api\Mobile\V1\PreferencesController as MobilePreferencesController;
use App\Http\Controllers\Webhooks\KorapayWebhookController;
use App\Http\Middleware\VerifyKorapayWebhookSignature;
use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [StorefrontAuthController::class, 'register']);
    Route::post('login', [StorefrontAuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('logout', [StorefrontAuthController::class, 'logout']);
        Route::get('user', [StorefrontAuthController::class, 'user']);
    });

    Route::prefix('account')->group(function () {
        Route::get('profile', [StorefrontAccountController::class, 'profile']);
        Route::patch('profile', [StorefrontAccountController::class, 'updateProfile']);
        Route::put('password', [StorefrontAccountController::class, 'updatePassword']);
        Route::post('delete', [StorefrontAccountController::class, 'delete']);
        Route::get('addresses', [StorefrontAddressController::class, 'index']);
        Route::post('addresses', [StorefrontAddressController::class, 'store']);
        Route::patch('addresses/{address}', [StorefrontAddressController::class, 'update']);
        Route::delete('addresses/{address}', [StorefrontAddressController::class, 'destroy']);
        Route::get('payment-methods', [StorefrontPaymentMethodController::class, 'index']);
        Route::post('payment-methods', [StorefrontPaymentMethodController::class, 'store']);
        Route::delete('payment-methods/{paymentMethod}', [StorefrontPaymentMethodController::class, 'destroy']);
    });
});

Route::prefix('storefront')->group(function () {
    Route::get('home', HomeController::class);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category:slug}', [CategoryController::class, 'show']);
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product:slug}', [ProductController::class, 'show']);
    Route::get('orders/track', TrackingController::class);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order:number}', [OrderController::class, 'show']);
    });
});

Route::prefix('mobile/v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [MobileAuthController::class, 'register']);
        Route::post('login', [MobileAuthController::class, 'login']);
    });

    Route::get('home', [MobileHomeController::class, 'index']);
    Route::get('categories', [MobileCategoryController::class, 'index']);
    Route::get('categories/{category:slug}', [MobileCategoryController::class, 'show']);
    Route::get('products', [MobileProductController::class, 'index']);
    Route::get('products/{product:slug}', [MobileProductController::class, 'show']);
    Route::get('products/{product:slug}/reviews', [MobileProductReviewController::class, 'index']);
    Route::get('orders/track', [MobileOrderController::class, 'track']);
    Route::get('preferences/lookups', [MobilePreferencesController::class, 'lookups']);
    Route::post('newsletter/subscribe', [MobileNewsletterController::class, 'subscribe']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('account')->group(function () {
            Route::get('addresses', [MobileAddressController::class, 'index']);
            Route::post('addresses', [MobileAddressController::class, 'store']);
            Route::patch('addresses/{address}', [MobileAddressController::class, 'update']);
            Route::delete('addresses/{address}', [MobileAddressController::class, 'destroy']);
            Route::get('payment-methods', [MobilePaymentMethodController::class, 'index']);
            Route::post('payment-methods', [MobilePaymentMethodController::class, 'store']);
            Route::delete('payment-methods/{paymentMethod}', [MobilePaymentMethodController::class, 'destroy']);
        });

        Route::prefix('auth')->group(function () {
            Route::post('logout', [MobileAuthController::class, 'logout']);
            Route::get('me', [MobileAuthController::class, 'me']);
            Route::patch('profile', [MobileAuthController::class, 'updateProfile']);
            Route::post('verify/resend', [MobileAuthController::class, 'resendVerification']);
            Route::post('verify/email', [MobileAuthController::class, 'verifyEmailOtp']);
            Route::post('phone/send-otp', [MobileAuthController::class, 'sendPhoneOtp']);
            Route::post('phone/verify-otp', [MobileAuthController::class, 'verifyPhoneOtp']);
        });

        Route::get('orders', [MobileOrderController::class, 'index']);
        Route::get('orders/{order:number}', [MobileOrderController::class, 'show']);

        Route::get('cart', [MobileCartController::class, 'show']);
        Route::post('cart/items', [MobileCartController::class, 'store']);
        Route::patch('cart/items/{itemId}', [MobileCartController::class, 'update']);
        Route::delete('cart/items/{itemId}', [MobileCartController::class, 'destroy']);
        Route::post('cart/apply-coupon', [MobileCartController::class, 'applyCoupon']);
        Route::post('cart/remove-coupon', [MobileCartController::class, 'removeCoupon']);

        Route::get('wishlist', [MobileWishlistController::class, 'index']);
        Route::post('wishlist/{productId}', [MobileWishlistController::class, 'store']);
        Route::delete('wishlist/{productId}', [MobileWishlistController::class, 'destroy']);

        Route::get('notifications', [MobileNotificationController::class, 'index']);
        Route::post('notifications/mark-read', [MobileNotificationController::class, 'markRead']);
      
        Route::post('checkout/preview', [MobileCheckoutController::class, 'preview']);
        Route::post('checkout/confirm', [MobileCheckoutController::class, 'confirm']);

        Route::post('products/{product:slug}/reviews', [MobileProductReviewController::class, 'store']);

        Route::get('preferences', [MobilePreferencesController::class, 'show']);
        Route::patch('preferences', [MobilePreferencesController::class, 'update']);

        Route::get('rewards/summary', [MobileRewardsController::class, 'summary']);
        Route::get('rewards/vouchers', [MobileRewardsController::class, 'vouchers']);
        Route::get('wallet', [MobileWalletController::class, 'show']);

        Route::post('payments/korapay/init', [MobilePaymentController::class, 'init']);
        Route::get('payments/korapay/verify', [MobilePaymentController::class, 'verify']);
    });
      Route::post('notifications/expo-token', [MobileNotificationController::class, 'registerExpoToken']);
        Route::delete('notifications/expo-token', [MobileNotificationController::class, 'removeExpoToken']);


});

Route::post('webhooks/korapay', KorapayWebhookController::class)
    ->middleware(['throttle:30,1', VerifyKorapayWebhookSignature::class, IdempotencyMiddleware::class])
    ->name('webhooks.korapay');
