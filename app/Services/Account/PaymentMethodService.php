<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\Customer;
use App\Models\PaymentMethod;
use Illuminate\Support\Collection;

class PaymentMethodService
{
    public function listForCustomer(Customer $customer): Collection
    {
        return PaymentMethod::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get();
    }

    public function createForCustomer(Customer $customer, array $data): PaymentMethod
    {
        $providerRef = $data['provider_ref'] ?? null;
        unset($data['provider_ref']);

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        if ($providerRef) {
            $meta['provider_ref'] = $providerRef;
        }

        if ($meta !== []) {
            $data['meta'] = $meta;
        }

        $data['customer_id'] = $customer->id;
        $data['is_default'] = (bool) ($data['is_default'] ?? false);

        if ($data['is_default']) {
            PaymentMethod::query()
                ->where('customer_id', $customer->id)
                ->update(['is_default' => false]);
        }

        return PaymentMethod::create($data);
    }

    public function deleteForCustomer(Customer $customer, PaymentMethod $paymentMethod): void
    {
        $paymentMethod->delete();
    }
}
