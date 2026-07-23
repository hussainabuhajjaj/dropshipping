<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Cart\CartManagerContract;
use App\Contracts\Catalog\ProductCatalogContract;
use App\Contracts\Checkout\CheckoutContract;
use App\Contracts\Orders\OrderRepositoryContract;
use App\Contracts\Payments\PaymentProcessorContract;
use App\Contracts\User\PreferenceContract;
use App\Services\Cart\CartIdentityService;
use App\Services\User\UserPreferenceService;
use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Phase 3: contracts implemented by existing services
        $this->app->bind(CartManagerContract::class, CartIdentityService::class);
        $this->app->bind(PreferenceContract::class, UserPreferenceService::class);

        // Phase 4: repository implementations
        $this->app->bind(ProductCatalogContract::class, \App\Repositories\Api\ProductRepository::class);
        $this->app->bind(OrderRepositoryContract::class, \App\Repositories\Api\OrderRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
