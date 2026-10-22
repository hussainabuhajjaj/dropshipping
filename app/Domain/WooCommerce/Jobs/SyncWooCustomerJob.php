<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Jobs;

use App\Domain\WooCommerce\Services\WooCommerceCustomerSyncService;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncWooCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public function __construct(
        private readonly int $customerId,
    ) {
        $this->onQueue(config('woocommerce.queue', 'woocommerce'));
    }

    public function handle(WooCommerceCustomerSyncService $customerSync): void
    {
        if (! config('woocommerce.enabled', false)) {
            return;
        }

        $customer = Customer::query()->find($this->customerId);

        if (! $customer) {
            return;
        }

        $customerSync->syncCustomer($customer);
    }
}
