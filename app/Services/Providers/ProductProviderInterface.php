<?php

namespace App\Services\Providers;

interface ProductProviderInterface
{
    public function searchProducts(array $params): array;
    public function getProductDetails(string $externalId): array;
}
