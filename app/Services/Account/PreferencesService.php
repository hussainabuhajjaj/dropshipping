<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\Customer;
use App\Services\User\UserPreferenceService;

class PreferencesService
{
    public function lookups(): array
    {
        $languages = array_keys((array) config('localization.supported', ['en' => 'English', 'fr' => 'Français']));

        return [
            'countries' => [
                'Cote D\'Ivoire',
            ],
            'currencies' => array_values((array) config('currency.supported', ['USD', 'XOF'])),
            'sizes' => [
                'US',
                'EU',
                'UK',
                'Asia',
            ],
            'languages' => array_values($languages),
            'currency_rates' => app(UserPreferenceService::class)->getCurrencyRates(),
            'currency_decimals' => app(UserPreferenceService::class)->getCurrencyDecimals(),
        ];
    }

    public function defaults(): array
    {
        return [
            'country' => 'Cote D\'Ivoire',
            'currency' => 'USD',
            'size' => 'US',
            'language' => (string) config('app.locale', 'en'),
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
        $defaults = $this->defaults();

        $resolvedCurrency = $customer->preferred_currency
            ?? ($stored['currency'] ?? null)
            ?? $defaults['currency'];
        $resolvedLanguage = $customer->preferred_language
            ?? $customer->locale
            ?? ($stored['language'] ?? null)
            ?? $defaults['language'];

        $stored['currency'] = $this->normalizeCurrency(is_string($resolvedCurrency) ? $resolvedCurrency : $defaults['currency']);
        $stored['language'] = $this->normalizeLanguage(is_string($resolvedLanguage) ? $resolvedLanguage : $defaults['language']);

        return array_replace_recursive($defaults, $stored);
    }

    public function updatePreferences(Customer $customer, array $data): array
    {
        $current = $this->getPreferences($customer);
        $incoming = $data;

        if (isset($incoming['notifications']) && ! is_array($incoming['notifications'])) {
            unset($incoming['notifications']);
        }

        if (isset($incoming['currency']) && is_string($incoming['currency'])) {
            $incoming['currency'] = $this->normalizeCurrency($incoming['currency']);
            app(UserPreferenceService::class)->setCurrency($incoming['currency']);
            if ($customer->preferred_currency !== $incoming['currency']) {
                $customer->preferred_currency = $incoming['currency'];
            }
        }

        if (isset($incoming['language']) && is_string($incoming['language'])) {
            $incoming['language'] = $this->normalizeLanguage($incoming['language']);
            app(UserPreferenceService::class)->setLanguage($incoming['language']);
            if ($customer->preferred_language !== $incoming['language']) {
                $customer->preferred_language = $incoming['language'];
            }
            if ($customer->locale !== $incoming['language']) {
                $customer->locale = $incoming['language'];
            }
        }

        $updated = array_replace_recursive($current, $incoming);

        $metadata = is_array($customer->metadata ?? null) ? $customer->metadata : [];
        $metadata['preferences'] = $updated;
        $customer->metadata = $metadata;
        $customer->save();

        return $updated;
    }

    private function normalizeCurrency(string $currency): string
    {
        $value = strtoupper(trim($currency));
        $value = match ($value) {
            'USD ($)' => 'USD',
            'CFA (XFA)', 'CFA (XOF)', 'XFA', 'XFC', 'XAF' => 'XOF',
            default => $value,
        };

        $supported = array_values((array) config('currency.supported', ['USD', 'XOF']));
        if (! in_array($value, $supported, true)) {
            return (string) config('currency.base', 'USD');
        }

        return $value;
    }

    private function normalizeLanguage(string $language): string
    {
        $value = strtolower(trim($language));
        $value = match ($value) {
            'english' => 'en',
            'french', 'français', 'francais' => 'fr',
            default => substr($value, 0, 2),
        };

        $supported = array_keys((array) config('localization.supported', ['en' => 'English', 'fr' => 'Français']));
        if (! in_array($value, $supported, true)) {
            return (string) config('app.locale', 'en');
        }

        return $value;
    }
}
