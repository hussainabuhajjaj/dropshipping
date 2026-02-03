<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\Customer;

class PreferencesService
{
    public function lookups(): array
    {
        return [
            'countries' => [
                'Cote D\'Ivoire',

            ],
            'currencies' => [
                'USD ($)',
                'CFA (XFA)',

            ],
            'sizes' => [
                'US',
                'EU',
                'UK',
                'Asia',
            ],
            'languages' => [
                'English',
                'French',
            ],
        ];
    }

    public function defaults(): array
    {
        return [
            'country' => 'Cote D\'Ivoire',
            'currency' => 'CFA (XFA)',
            'size' => 'US',
            'language' => 'French',
            'notifications' => [
                'push' => false,
                'email' => false,
                'sms' => false,
            ],
        ];
    }

    public function getPreferences(Customer $customer): array
    {
        $metadata = is_array($customer->metadata ?? null) ? $customer->metadata : [];
        $stored = is_array($metadata['preferences'] ?? null) ? $metadata['preferences'] : [];

        return array_replace_recursive($this->defaults(), $stored);
    }

    public function updatePreferences(Customer $customer, array $data): array
    {
        $current = $this->getPreferences($customer);
        $incoming = $data;

        if (isset($incoming['notifications']) && ! is_array($incoming['notifications'])) {
            unset($incoming['notifications']);
        }

        $updated = array_replace_recursive($current, $incoming);

        $metadata = is_array($customer->metadata ?? null) ? $customer->metadata : [];
        $metadata['preferences'] = $updated;
        $customer->metadata = $metadata;
        $customer->save();

        return $updated;
    }
}
