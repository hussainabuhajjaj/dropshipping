<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Services;

use App\Domain\WooCommerce\Contracts\WooCommerceClientContract;
use App\Domain\WooCommerce\Contracts\WooCommerceCustomerSyncContract;
use App\Domain\WooCommerce\DTOs\WooCommerceSyncResult;
use App\Domain\WooCommerce\Models\WooCommerceCustomerMap;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

class WooCommerceCustomerSyncService implements WooCommerceCustomerSyncContract
{
    public function __construct(
        private readonly WooCommerceClientContract $client,
        private readonly WooCommerceLogService $log,
    ) {
    }

    public function syncCustomer(Customer $customer): WooCommerceSyncResult
    {
        try {
            $existing = WooCommerceCustomerMap::query()
                ->where('customer_id', $customer->id)
                ->first();

            if ($existing) {
                $this->client->updateCustomer($existing->woocommerce_customer_id, $this->buildPayload($customer));

                $existing->update([
                    'email' => $customer->email,
                    'status' => 'synced',
                    'last_error' => null,
                    'last_synced_at' => now(),
                ]);

                $this->log->info('customer', $customer->id, 'update', [
                    'woocommerce_customer_id' => $existing->woocommerce_customer_id,
                ]);

                return WooCommerceSyncResult::success($customer->id, $existing->woocommerce_customer_id);
            }

            $wooId = $this->createInWooCommerce($customer);

            WooCommerceCustomerMap::create([
                'customer_id' => $customer->id,
                'woocommerce_customer_id' => $wooId,
                'email' => $customer->email,
                'status' => 'synced',
                'last_synced_at' => now(),
            ]);

            $this->log->info('customer', $customer->id, 'create', [
                'woocommerce_customer_id' => $wooId,
            ]);

            return WooCommerceSyncResult::success($customer->id, $wooId);
        } catch (\Throwable $e) {
            WooCommerceCustomerMap::query()
                ->where('customer_id', $customer->id)
                ->update([
                    'status' => 'failed',
                    'last_error' => $e->getMessage(),
                ]);

            Log::error('WooCommerce customer sync failed', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return WooCommerceSyncResult::failed($e->getMessage(), $customer->id);
        }
    }

    public function findOrCreateWooCommerceCustomer(Customer $customer): int
    {
        $existing = WooCommerceCustomerMap::query()
            ->where('customer_id', $customer->id)
            ->first();

        if ($existing) {
            return $existing->woocommerce_customer_id;
        }

        $result = $this->syncCustomer($customer);

        if (! $result->success || $result->woocommerceId === null) {
            throw new \RuntimeException('Failed to sync customer to WooCommerce: ' . ($result->error ?? 'unknown error'));
        }

        return $result->woocommerceId;
    }

    public function updateCustomerFromWebhook(int $woocommerceCustomerId, array $data): WooCommerceSyncResult
    {
        $map = WooCommerceCustomerMap::query()
            ->where('woocommerce_customer_id', $woocommerceCustomerId)
            ->first();

        if (! $map || ! $map->customer) {
            return WooCommerceSyncResult::skipped('No local customer mapping found');
        }

        $customer = $map->customer;

        $updates = [];
        if (isset($data['email'])) {
            $updates['email'] = $data['email'];
        }
        if (isset($data['first_name'])) {
            $updates['first_name'] = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $updates['last_name'] = $data['last_name'];
        }

        if ($updates !== []) {
            $customer->update($updates);
            $map->update(['last_synced_at' => now()]);
        }

        return WooCommerceSyncResult::success($customer->id, $woocommerceCustomerId);
    }

    private function createInWooCommerce(Customer $customer): int
    {
        $existingWoo = $this->client->getCustomerByEmail($customer->email);

        if ($existingWoo !== null) {
            return $existingWoo->woocommerceId;
        }

        $response = $this->client->createCustomer($this->buildPayload($customer));

        return (int) ($response['id'] ?? 0);
    }

    private function buildPayload(Customer $customer): array
    {
        return [
            'email' => $customer->email,
            'first_name' => $customer->first_name ?? '',
            'last_name' => $customer->last_name ?? '',
            'billing' => [
                'first_name' => $customer->first_name ?? '',
                'last_name' => $customer->last_name ?? '',
                'email' => $customer->email,
                'phone' => $customer->phone ?? '',
                'address_1' => $customer->address_line1 ?? '',
                'address_2' => $customer->address_line2 ?? '',
                'city' => $customer->city ?? '',
                'state' => $customer->region ?? '',
                'postcode' => $customer->postal_code ?? '',
                'country' => $customer->country_code ?? '',
            ],
            'shipping' => [
                'first_name' => $customer->first_name ?? '',
                'last_name' => $customer->last_name ?? '',
                'address_1' => $customer->address_line1 ?? '',
                'address_2' => $customer->address_line2 ?? '',
                'city' => $customer->city ?? '',
                'state' => $customer->region ?? '',
                'postcode' => $customer->postal_code ?? '',
                'country' => $customer->country_code ?? '',
            ],
            'meta_data' => [
                ['key' => '_laravel_customer_id', 'value' => (string) $customer->id],
            ],
        ];
    }
}
