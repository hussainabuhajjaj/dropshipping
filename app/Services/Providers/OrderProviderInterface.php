<?php

namespace App\Services\Providers;

interface OrderProviderInterface
{
    public function createOrder(array $orderData): array;
    public function getOrder(string $externalId): array;
    public function trackOrder(string $externalId): array;
}
