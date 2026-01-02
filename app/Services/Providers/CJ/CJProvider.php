<?php

namespace App\Services\Providers\CJ;

use App\Services\Providers\ProductProviderInterface;
use App\Services\Providers\OrderProviderInterface;

class CJProvider implements ProductProviderInterface, OrderProviderInterface
{
    public function searchProducts(array $params): array
    {
        // TODO: Implement CJ product search API call
        return [];
    }

    public function getProductDetails(string $externalId): array
    {
        // TODO: Implement CJ product details API call
        return [];
    }

    public function createOrder(array $orderData): array
    {
        // TODO: Implement CJ order creation API call
        return [];
    }

    public function getOrder(string $externalId): array
    {
        // TODO: Implement CJ get order API call
        return [];
    }

    public function trackOrder(string $externalId): array
    {
        // TODO: Implement CJ order tracking API call
        return [];
    }
}
