<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Orders\Models\OrderItem;

class SupplierDecisionService
{
    public function __construct(private readonly SupplierPerformanceService $performanceService)
    {
    }

    /**
     * Choose the best available provider for an order item, respecting overrides and blacklists.
     */
    public function selectProvider(OrderItem $item): FulfillmentProvider
    {
        // Manual override on the order item
        if ($item->fulfillmentProvider && $this->isUsable($item->fulfillmentProvider)) {
            return $item->fulfillmentProvider;
        }

        // Supplier product preferred provider
        if ($item->supplierProduct?->fulfillmentProvider && $this->isUsable($item->supplierProduct->fulfillmentProvider)) {
            return $item->supplierProduct->fulfillmentProvider;
        }

        // Product default provider
        $defaultProvider = $item->productVariant?->product?->defaultFulfillmentProvider;
        if ($defaultProvider && $this->isUsable($defaultProvider)) {
            return $defaultProvider;
        }

        // Fallback: best performing active provider
        $provider = FulfillmentProvider::query()
            ->where('is_active', true)
            ->where('is_blacklisted', false)
            ->with('metrics')
            ->get()
            ->sortBy(function (FulfillmentProvider $provider) {
                $m = $provider->metrics;
                if (! $m) {
                    return PHP_INT_MAX;
                }
                $failRate = $m->fulfilled_count + $m->failed_count > 0
                    ? $m->failed_count / ($m->fulfilled_count + $m->failed_count)
                    : 1;
                return [$failRate, $m->average_lead_time_days ?? 9999];
            })
            ->first();

        if ($provider) {
            return $provider;
        }

        throw new FulfillmentException('No available fulfillment provider (all inactive or blacklisted).');
    }

    private function isUsable(FulfillmentProvider $provider): bool
    {
        return $provider->is_active && ! $provider->is_blacklisted;
    }
}
