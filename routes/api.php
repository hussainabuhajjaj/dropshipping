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
use App\Http\Controllers\Storefront\UserPreferenceController;
use App\Http\Controllers\CjApiMonitoringController;
use App\Http\Controllers\Api\Mobile\V1\AuthController as MobileAuthController;
use App\Http\Controllers\Api\Mobile\V1\HomeController as MobileHomeController;
use App\Http\Controllers\Api\Mobile\V1\CategoryController as MobileCategoryController;
use App\Http\Controllers\Api\Mobile\V1\ProductController as MobileProductController;
use App\Http\Controllers\Api\Mobile\V1\ProductReviewController as MobileProductReviewController;
use App\Http\Controllers\Api\Mobile\V1\CartController as MobileCartController;
use App\Http\Controllers\Api\Mobile\V1\CheckoutController as MobileCheckoutController;
use App\Http\Controllers\Api\Mobile\V1\PaymentMonitoringController;
use App\Http\Controllers\Api\WhatsAppOrderIntentController;
use Illuminate\Support\Facades\Route;

if (app()->environment('local')) {
    Route::prefix('debug')->group(function () {
        Route::get('/session', function () {
            $localeCookie = request()->cookie('locale');
            $decryptedLocale = null;
            $sessionDecryptError = null;

            if ($localeCookie) {
                try {
                    $decryptedLocale = decrypt($localeCookie);
                } catch (\Throwable $exception) {
                    $decryptedLocale = 'Failed to decrypt: ' . $exception->getMessage();
                }
            }

            $sessionCookie = request()->cookie(config('session.cookie', 'simbazu-session'));
            $decryptedSession = null;

            if ($sessionCookie) {
                try {
                    $decryptedSession = decrypt($sessionCookie);
                } catch (\Throwable $exception) {
                    $sessionDecryptError = $exception->getMessage();
                    $decryptedSession = 'Failed to decrypt: ' . $sessionDecryptError;
                }
            }

            $currentAppKey = config('app.key');
            $appKeyHash = substr(md5((string) $currentAppKey), 0, 8);

            return response()->json([
                'session_id' => session()->getId(),
                'session_data' => session()->all(),
                'session_has_data' => session()->has('user_currency') || session()->has('locale'),
                'auth_check' => [
                    'web' => auth()->check(),
                    'customer' => auth()->guard('customer')->check(),
                ],
                'customer_auth' => auth()->guard('customer')->user() ? [
                    'id' => auth()->guard('customer')->user()->id,
                    'email' => auth()->guard('customer')->user()->email,
                ] : null,
                'decrypted_data' => [
                    'locale_cookie' => $decryptedLocale,
                    'session_cookie' => $decryptedSession,
                ],
                'app_key_info' => [
                    'current_key_hash' => $appKeyHash,
                    'key_length' => strlen((string) $currentAppKey),
                    'encryption_issue' => is_string($sessionDecryptError) && str_contains($sessionDecryptError, 'unserialize'),
                ],
            ]);
        });

        Route::get('/clear-cookies', function () {
            $cookiesToClear = [
                'locale',
                config('session.cookie', 'simbazu-session'),
                'XSRF-TOKEN',
            ];

            $cleared = [];
            foreach ($cookiesToClear as $cookie) {
                if (request()->cookie($cookie)) {
                    $cleared[] = $cookie;
                    cookie()->queue(cookie()->forget($cookie, '/'));
                }
            }

            return response()->json([
                'message' => 'Cookies cleared successfully',
                'cleared_cookies' => $cleared,
                'instruction' => 'Please refresh the page to continue',
            ]);
        });

        Route::get('/reset-session', function () {
            session()->invalidate();
            session()->regenerateToken();

            return response()->json([
                'message' => 'Session reset at Laravel level',
                'new_session_id' => session()->getId(),
                'instruction' => 'Please close browser completely and re-login',
            ]);
        });
    });
}

use App\Http\Controllers\Api\Mobile\V1\OrderController as MobileOrderController;
use App\Http\Controllers\Api\Mobile\V1\PaymentController as MobilePaymentController;
use App\Http\Controllers\Api\Mobile\V1\PaymentCallbackController as MobilePaymentCallbackController;
use App\Http\Controllers\Api\Mobile\V1\AddressController as MobileAddressController;
use App\Http\Controllers\Api\Mobile\V1\PaymentMethodController as MobilePaymentMethodController;
use App\Http\Controllers\Api\Mobile\V1\WishlistController as MobileWishlistController;
use App\Http\Controllers\Api\Mobile\V1\NotificationController as MobileNotificationController;
use App\Http\Controllers\Api\Mobile\V1\NewsletterController as MobileNewsletterController;
use App\Http\Controllers\Api\Mobile\V1\RewardsController as MobileRewardsController;
use App\Http\Controllers\Api\Mobile\V1\WalletController as MobileWalletController;
use App\Http\Controllers\Api\Mobile\V1\PreferencesController as MobilePreferencesController;
use App\Http\Controllers\Api\Mobile\V1\SearchController as MobileSearchController;
use App\Http\Controllers\Api\Mobile\V1\TranslationsController as MobileTranslationsController;
use App\Http\Controllers\Api\Mobile\V1\OnboardingController as MobileOnboardingController;
use App\Http\Controllers\Api\Mobile\V1\AnnouncementController as MobileAnnouncementController;
use App\Http\Controllers\Api\Mobile\V1\ChatController as MobileChatController;
use App\Http\Controllers\Api\Mobile\V1\CollectionController as MobileCollectionController;
use App\Http\Controllers\Api\Mobile\V1\PaymentDebugController;
use App\Http\Controllers\Api\Mobile\V1\LegalController as MobileLegalController;
use App\Http\Controllers\Api\Mobile\V1\StoryController as MobileStoryController;
use App\Http\Controllers\Api\Mobile\V1\VisitAnalyticsController as MobileVisitAnalyticsController;
use App\Http\Controllers\Webhooks\KorapayWebhookController;
use App\Http\Middleware\VerifyKorapayWebhookSignature;
use App\Http\Middleware\IdempotencyMiddleware;

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

// User Preferences API
Route::middleware('web')->group(function () {
    Route::get('user-preferences', [UserPreferenceController::class, 'index']);
    Route::put('user-preferences/currency', [UserPreferenceController::class, 'updateCurrency']);
    Route::put('user-preferences/language', [UserPreferenceController::class, 'updateLanguage']);
    Route::put('user-preferences', [UserPreferenceController::class, 'update']);
});

Route::get('currency-settings', function () {
    $rates = config('currency.rates', []);
    $baseCurrency = config('currency.base', 'USD');

    // Build comprehensive rates array including base currency
    $allRates = [$baseCurrency => 1.0];

    foreach ($rates as $rateKey => $rateValue) {
        if (str_contains($rateKey, '_')) {
            [$from, $to] = explode('_', $rateKey);
            $allRates[$from] = $allRates[$from] ?? 1.0;
            $allRates[$to] = $rateValue;
        }
    }

    return response()->json([
        'supported' => ['XOF'],
        'rates' => $allRates,
        'base' => $baseCurrency,
        'decimals' => config('currency.decimals', []),
        'display' => [
            'auto_convert_prices' => cache('currency_auto_convert', true),
            'show_currency_selector' => false,
            'default_customer_currency' => 'XOF',
        ]
    ]);
});

// Guest-accessible payment verification endpoint
Route::get('payments/verify', [\App\Http\Controllers\Api\PaymentVerificationController::class, '__invoke']);

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

// Backward compatibility: alias /api/mobile/auth/* (without v1) to the same controllers
Route::prefix('mobile/auth')->group(function () {
    Route::post('register', [MobileAuthController::class, 'register']);
    Route::post('login', [MobileAuthController::class, 'login']);
    Route::post('social/exchange', [MobileAuthController::class, 'exchangeSocialCode']);
    Route::post('forgot-password', [MobileAuthController::class, 'forgotPassword']);
    Route::post('reset-password', [MobileAuthController::class, 'resetPassword']);
});

Route::prefix('mobile/v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [MobileAuthController::class, 'register']);
        Route::post('login', [MobileAuthController::class, 'login']);
        Route::post('social/exchange', [MobileAuthController::class, 'exchangeSocialCode']);
        Route::post('forgot-password', [MobileAuthController::class, 'forgotPassword']);
        Route::post('reset-password', [MobileAuthController::class, 'resetPassword']);
    });

Route::get('onboarding', [MobileOnboardingController::class, 'index']);
    Route::get('announcements', [MobileAnnouncementController::class, 'index']);
    Route::get('home', [MobileHomeController::class, 'index']);
    Route::get('categories', [MobileCategoryController::class, 'index']);
    Route::get('categories/{category:slug}', [MobileCategoryController::class, 'show']);
    Route::get('products', [MobileProductController::class, 'index']);
    Route::get('products/{product:slug}', [MobileProductController::class, 'show']);
    Route::get('products/{product:slug}/reviews', [MobileProductReviewController::class, 'index']);
    Route::get('collections', [MobileCollectionController::class, 'index']);
    Route::get('collections/{slug}', [MobileCollectionController::class, 'show']);
    Route::get('campaigns/{slug}', [\App\Http\Controllers\Api\Mobile\V1\CampaignController::class, 'show']);
    Route::get('campaigns/{slug}/winners', [\App\Http\Controllers\Api\Mobile\V1\CampaignController::class, 'winners']);
    Route::get('search', [MobileSearchController::class, 'index']);
    Route::get('translations', [MobileTranslationsController::class, 'index']);
    Route::post('translations/register', [MobileTranslationsController::class, 'register']);
    Route::get('orders/track', [MobileOrderController::class, 'track']);
    Route::get('preferences/lookups', [MobilePreferencesController::class, 'lookups']);
    Route::post('newsletter/subscribe', [MobileNewsletterController::class, 'subscribe']);
    Route::get('legal', [MobileLegalController::class, 'index']);
    Route::get('legal/{slug}', [MobileLegalController::class, 'show']);
    Route::get('stories', [MobileStoryController::class, 'index']);
    Route::get('stories/{id}', [MobileStoryController::class, 'show']);
    Route::get('rewards/claim/{slug}', [MobileRewardsController::class, 'showClaim']);
    Route::post('analytics/visit', [MobileVisitAnalyticsController::class, 'store']);
    Route::post('whatsapp-intents', [WhatsAppOrderIntentController::class, 'store']);
    Route::get('whatsapp-intents/{reference}', [WhatsAppOrderIntentController::class, 'show']);
    Route::get('cart', [MobileCartController::class, 'show']);
    Route::post('cart/items', [MobileCartController::class, 'store']);
    Route::patch('cart/items/{itemId}', [MobileCartController::class, 'update']);
    Route::delete('cart/items/{itemId}', [MobileCartController::class, 'destroy']);
    Route::post('cart/apply-coupon', [MobileCartController::class, 'applyCoupon']);
    Route::post('cart/remove-coupon', [MobileCartController::class, 'removeCoupon']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('logout', [MobileAuthController::class, 'logout']);
            Route::post('verify/resend', [MobileAuthController::class, 'resendVerification']);
            Route::post('verify/email', [MobileAuthController::class, 'verifyEmailOtp']);
        });

        Route::prefix('account')->group(function () {
            Route::delete('delete', [MobileAuthController::class, 'deleteAccount']);
        });

        Route::middleware('mobile.email.verified')->group(function () {
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
                Route::get('me', [MobileAuthController::class, 'me']);
                Route::patch('profile', [MobileAuthController::class, 'updateProfile']);
                Route::post('phone/send-otp', [MobileAuthController::class, 'sendPhoneOtp']);
                Route::post('phone/verify-otp', [MobileAuthController::class, 'verifyPhoneOtp']);
            });

            Route::get('orders', [MobileOrderController::class, 'index']);
            Route::get('orders/{order:number}', [MobileOrderController::class, 'show']);

            Route::get('campaigns/{slug}/my-entry', [\App\Http\Controllers\Api\Mobile\V1\CampaignController::class, 'myEntry']);
            Route::get('campaigns/{slug}/my-rewards', [\App\Http\Controllers\Api\Mobile\V1\CampaignController::class, 'myRewards']);

            Route::get('wishlist', [MobileWishlistController::class, 'index']);
            Route::post('wishlist/{productId}', [MobileWishlistController::class, 'store']);
            Route::delete('wishlist/{productId}', [MobileWishlistController::class, 'destroy']);

            Route::get('notifications', [MobileNotificationController::class, 'index']);
            Route::post('notifications/mark-read', [MobileNotificationController::class, 'markRead']);
            Route::post('notifications/expo-token', [MobileNotificationController::class, 'registerExpoToken']);
            Route::delete('notifications/expo-token', [MobileNotificationController::class, 'removeExpoToken']);

            Route::middleware('throttle:checkout')->group(function () {
                Route::post('checkout/preview', [MobileCheckoutController::class, 'preview']);
                Route::post('checkout/confirm', [MobileCheckoutController::class, 'confirm'])
                    ->middleware(IdempotencyMiddleware::class);
            });

            Route::post('products/{product:slug}/reviews', [MobileProductReviewController::class, 'store']);

            Route::get('preferences', [MobilePreferencesController::class, 'show']);
            Route::patch('preferences', [MobilePreferencesController::class, 'update']);
        });

        Route::middleware('mobile.email.verified')->group(function () {
            Route::get('rewards/summary', [MobileRewardsController::class, 'summary']);
            Route::get('rewards/vouchers', [MobileRewardsController::class, 'vouchers']);
            Route::get('rewards/claim/{slug}', [MobileRewardsController::class, 'showClaim']);
    Route::post('rewards/claim/{slug}', [MobileRewardsController::class, 'claim']);
    Route::get('wallet', [MobileWalletController::class, 'show']);

            // Legacy Korapay routes (for backward compatibility)
            Route::post('payments/korapay/init', [MobilePaymentController::class, 'init']);
            Route::get('payments/korapay/verify', [MobilePaymentController::class, 'verify']);

            // Unified payment routes (matching storefront exactly)
            Route::post('payments/initialize', [MobilePaymentController::class, 'initialize'])
                ->middleware(IdempotencyMiddleware::class);
            Route::post('payments/checkout', [MobilePaymentController::class, 'initialize']); // Alias for storefront compatibility
            Route::get('payments/redirect', [MobilePaymentController::class, 'redirect']);
            Route::post('payments/verify', [MobilePaymentController::class, 'verifyPayment']);
            Route::get('payments/methods', [MobilePaymentController::class, 'methods']);

            // Mobile payment callback routes (matching storefront pattern)
            Route::post('payments/callback', [MobilePaymentCallbackController::class, 'handle']);
            Route::post('payments/cancel', [MobilePaymentCallbackController::class, 'cancel']);

            // Payment debug routes for testing
            Route::get('payments/debug/redirect-url', [PaymentDebugController::class, 'testRedirectUrl']);
            Route::post('payments/debug/simulate-redirect', [PaymentDebugController::class, 'simulateRedirect']);
            Route::get('payments/debug/data', [PaymentDebugController::class, 'getPaymentData']);

            // Payment monitoring routes
            Route::get('payments/monitoring/dashboard', [PaymentMonitoringController::class, 'dashboard']);
            Route::get('payments/monitoring/health', [PaymentMonitoringController::class, 'healthCheck']);
            Route::get('payments/monitoring/statistics', [PaymentMonitoringController::class, 'statistics']);

            Route::post('chat/start', [MobileChatController::class, 'start']);
            Route::post('chat/respond', [MobileChatController::class, 'respond']);
            Route::post('chat/forward', [MobileChatController::class, 'forward']);
            Route::post('chat/attachment', [MobileChatController::class, 'attachment']);
            Route::get('chat/messages', [MobileChatController::class, 'messages']);
        });
    });
});

Route::post('webhooks/korapay', KorapayWebhookController::class)
    ->middleware(['throttle:30,1', VerifyKorapayWebhookSignature::class, IdempotencyMiddleware::class])
    ->name('webhooks.korapay');

Route::get('webhooks/meta', [\App\Http\Controllers\Webhooks\MetaWebhookController::class, 'verify'])
    ->middleware('throttle:30,1')
    ->name('webhooks.meta.verify');

Route::post('webhooks/meta', [\App\Http\Controllers\Webhooks\MetaWebhookController::class, 'receive'])
    ->middleware('throttle:60,1')
    ->name('webhooks.meta.receive');

Route::post('integrations/woocommerce/webhook', \App\Domain\WooCommerce\Webhooks\WooCommerceWebhookController::class)
    ->middleware(['throttle:60,1'])
    ->name('woocommerce.webhook');

// CJ API Monitoring Routes
Route::prefix('cj')->group(function () {
    Route::get('health', [CjApiMonitoringController::class, 'health']);
    Route::get('metrics', [CjApiMonitoringController::class, 'metrics']);
    Route::post('circuit-breaker/reset', [CjApiMonitoringController::class, 'resetCircuitBreaker']);
});
