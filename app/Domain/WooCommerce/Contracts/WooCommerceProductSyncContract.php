<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Contracts;

use App\Domain\Products\Models\Product;
use App\Domain\WooCommerce\DTOs\WooCommerceSyncResult;

interface WooCommerceProductSyncContract
{
    public function syncProduct(Product $product): WooCommerceSyncResult;

    public function syncProducts(array $productIds): array;

    public function deleteProduct(Product $product): WooCommerceSyncResult;

    public function importProductFromWooCommerce(int $woocommerceProductId, ?int $variationId = null): WooCommerceSyncResult;

    public function computeSyncHash(Product $product): string;

    public function needsSync(Product $product): bool;
}
