<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Domain\Fulfillment\Contracts\FulfillmentStrategy;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Orders\Models\OrderItem;
use Illuminate\Contracts\Container\Container;

class FulfillmentSelector
{
    public function __construct(
        private readonly Container $container,
        private readonly SupplierDecisionService $decisionService,
    ) {
    }

    public function resolveForOrderItem(OrderItem $orderItem): FulfillmentStrategy
    {
        $provider = $this->determineProvider($orderItem);

        $driverClass = $this->mapLegacyDriver($provider->driver_class, $provider->code);

        if (! class_exists($driverClass)) {
            throw new FulfillmentException("Fulfillment driver not found for provider [{$provider->code}]");
        }

        if (! is_subclass_of($driverClass, FulfillmentStrategy::class)) {
            throw new FulfillmentException("Fulfillment driver for provider [{$provider->code}] must implement FulfillmentStrategy");
        }

        // Only allow strategy classes within the expected namespace to avoid arbitrary class loading.
        if (! str_starts_with($driverClass, 'App\\Domain\\Fulfillment\\Strategies\\')) {
            throw new FulfillmentException("Fulfillment driver for provider [{$provider->code}] is not in the allowed namespace");
        }

        /** @var FulfillmentStrategy $strategy */
        $strategy = $this->container->make($driverClass, ['provider' => $provider]);

        return $strategy;
    }

    private function determineProvider(OrderItem $orderItem): FulfillmentProvider
    {
        $provider = $orderItem->fulfillmentProvider
            ?? $orderItem->supplierProduct?->fulfillmentProvider
            ?? $orderItem->productVariant?->product?->defaultFulfillmentProvider;

        if ($provider && $provider->is_active && ! $provider->is_blacklisted) {
            return $provider;
        }

        return $this->decisionService->selectProvider($orderItem);
    }

    private function mapLegacyDriver(?string $driverClass, string $providerCode): string
    {
        if ($driverClass && str_starts_with($driverClass, 'App\\Domain\\Fulfillment\\Strategies\\')) {
            return $driverClass;
        }

        $map = [
            'App\\Infrastructure\\Fulfillment\\Drivers\\ManualDriver' => \App\Domain\Fulfillment\Strategies\ManualFulfillmentStrategy::class,
            'App\\Infrastructure\\Fulfillment\\Drivers\\SupplierDriver' => \App\Domain\Fulfillment\Strategies\ManualFulfillmentStrategy::class,
            'App\\Infrastructure\\Fulfillment\\Drivers\\AliExpressDriver' => \App\Domain\Fulfillment\Strategies\AliExpressFulfillmentStrategy::class,
            null => \App\Domain\Fulfillment\Strategies\ManualFulfillmentStrategy::class,
        ];

        if (isset($map[$driverClass])) {
            return $map[$driverClass];
        }

        throw new FulfillmentException("Fulfillment driver not found for provider [{$providerCode}]");
    }
}
