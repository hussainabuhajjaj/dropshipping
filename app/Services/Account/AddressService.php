<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Domain\Common\Models\Address;
use App\Models\Customer;
use Illuminate\Support\Collection;

class AddressService
{
    public function listForCustomer(Customer $customer): Collection
    {
        return Address::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get();
    }

    public function createForCustomer(Customer $customer, array $data): Address
    {
        $data['customer_id'] = $customer->id;
        $data['country'] = $data['country'] ?? null;
        $data['type'] = $data['type'] ?? null;
        $data['country'] = $data['country'] ?: 'CI';
        $data['type'] = $data['type'] ?: 'shipping';
        $data['is_default'] = (bool) ($data['is_default'] ?? false);

        if (! Address::query()->where('customer_id', $customer->id)->exists()) {
            $data['is_default'] = true;
        }

        if ($data['is_default']) {
            Address::query()
                ->where('customer_id', $customer->id)
                ->update(['is_default' => false]);
        }

        return Address::create($data);
    }

    public function updateForCustomer(Customer $customer, Address $address, array $data): Address
    {
        $address->update($data);

        if (! empty($data['is_default'])) {
            Address::query()
                ->where('customer_id', $customer->id)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        return $address->fresh();
    }

    public function deleteForCustomer(Customer $customer, Address $address): void
    {
        $address->delete();
    }
}
