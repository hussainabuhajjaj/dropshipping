<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Fulfillment\Contracts\FulfillmentStrategy;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Fulfillment\Services\FulfillmentSelector;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class FulfillmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FulfillmentSelector::class);

        // Bind CJDropshippingClient to use fromConfig() factory method
        $this->app->singleton(CJDropshippingClient::class, function () {
            return new CJDropshippingClient();
        });

        $this->app->bind(FulfillmentStrategy::class, function (Container $app, array $context) {
            /** @var FulfillmentProvider|null $provider */
            $provider = $context['provider'] ?? null;

            if (! $provider) {
                throw new FulfillmentException('Fulfillment provider is required to resolve a strategy.');
            }

            return $app->make($provider->driver_class, ['provider' => $provider]);
        });
    }

    public function boot(): void
    {
        //
    }
}
