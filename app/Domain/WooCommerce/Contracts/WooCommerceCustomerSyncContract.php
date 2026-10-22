<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Contracts;

use App\Domain\WooCommerce\DTOs\WooCommerceSyncResult;
use App\Models\Customer;

interface WooCommerceCustomerSyncContract
{
    public function syncCustomer(Customer $customer): WooCommerceSyncResult;

    public function findOrCreateWooCommerceCustomer(Customer $customer): int;

    public function updateCustomerFromWebhook(int $woocommerceCustomerId, array $data): WooCommerceSyncResult;
}
