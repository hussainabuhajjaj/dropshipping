<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\WooCommerce\Contracts\WooCommerceClientContract;
use App\Domain\WooCommerce\Contracts\WooCommerceCustomerSyncContract;
use App\Domain\WooCommerce\Contracts\WooCommerceOrderSyncContract;
use App\Domain\WooCommerce\Contracts\WooCommerceProductSyncContract;
use App\Domain\WooCommerce\Filament\WooCommerceSettingsPage;
use App\Domain\WooCommerce\Filament\WooCommerceSyncLogResource;
use App\Domain\WooCommerce\Filament\WooCommerceWebhookLogResource;
use App\Domain\WooCommerce\Services\WooCommerceCustomerSyncService;
use App\Domain\WooCommerce\Services\WooCommerceLogService;
use App\Domain\WooCommerce\Services\WooCommerceOrderSyncService;
use App\Domain\WooCommerce\Services\WooCommerceProductSyncService;
use App\Domain\WooCommerce\Webhooks\WooCommerceWebhookVerifier;
use App\Infrastructure\WooCommerce\WooCommerceClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class WooCommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/woocommerce.php',
            'woocommerce',
        );

        $this->app->singleton(WooCommerceClientContract::class, function (Application $app) {
            return new WooCommerceClient();
        });

        $this->app->singleton(WooCommerceWebhookVerifier::class);
        $this->app->singleton(WooCommerceLogService::class);

        $this->app->singleton(WooCommerceProductSyncContract::class, function (Application $app) {
            return new WooCommerceProductSyncService(
                client: $app->make(WooCommerceClientContract::class),
                pricing: $app->make(\App\Domain\Products\Services\PricingService::class),
                log: $app->make(WooCommerceLogService::class),
            );
        });

        $this->app->singleton(WooCommerceCustomerSyncContract::class, function (Application $app) {
            return new WooCommerceCustomerSyncService(
                client: $app->make(WooCommerceClientContract::class),
                log: $app->make(WooCommerceLogService::class),
            );
        });

        $this->app->singleton(WooCommerceOrderSyncContract::class, function (Application $app) {
            return new WooCommerceOrderSyncService(
                client: $app->make(WooCommerceClientContract::class),
                customerSync: $app->make(WooCommerceCustomerSyncContract::class),
                log: $app->make(WooCommerceLogService::class),
            );
        });

        $this->app->singleton(\App\Domain\WooCommerce\Services\WooCommerceWebhookHandlerService::class);
    }

    public function boot(): void
    {
        if (! config('woocommerce.enabled', false)) {
            return;
        }

        $this->registerFilamentPages();
        $this->registerObservers();
    }

    private function registerFilamentPages(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'filament');
    }

    private function registerObservers(): void
    {
        \App\Domain\Products\Models\Product::observe(
            \App\Domain\WooCommerce\Listeners\SyncProductToWooCommerce::class,
        );
    }
}
