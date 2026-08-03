<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Contracts;

use App\Domain\Orders\Models\Order;
use App\Domain\WooCommerce\DTOs\WooCommerceSyncResult;

interface WooCommerceOrderSyncContract
{
    public function syncOrder(Order $order): WooCommerceSyncResult;

    public function createWooCommerceOrder(Order $order): WooCommerceSyncResult;

    public function updateOrderStatusFromWebhook(int $woocommerceOrderId, string $status): WooCommerceSyncResult;

    public function updateTrackingFromWebhook(int $woocommerceOrderId, array $trackingData): WooCommerceSyncResult;
}
