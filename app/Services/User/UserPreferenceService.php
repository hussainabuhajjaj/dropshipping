<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Models\Customer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class UserPreferenceService
{
    private const CACHE_PREFIX = 'user_preferences';
    private const CACHE_TTL = 1800; // 30 minutes

    /**
     * Get user preferences for current user/guest
     */
    public function getPreferences(): array
    {
        $key = $this->getCacheKey();

        return Cache::remember($key, self::CACHE_TTL, function () {
            return $this->fetchPreferences();
        });
    }

    /**
     * Update user currency preference
     */
    public function setCurrency(string $currency): void
    {
        $this->validateCurrency($currency);

        $customer = $this->resolveCustomer();
        if ($customer) {
            $this->updateCustomerPreference($customer, 'preferred_currency', $currency);
        }

        Session::put('user_currency', $currency);
        // dd(Session::all());

        // Clear cache
        $this->clearCache();

        $currencyPreferenceChangedEvent = \App\Events\User\CurrencyPreferenceChanged::class;
        if (class_exists($currencyPreferenceChangedEvent)) {
            try {
                event(new $currencyPreferenceChangedEvent($currency));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * Update user language preference
     */
    public function setLanguage(string $language): void
    {
        $this->validateLanguage($language);

        $customer = $this->resolveCustomer();
        if ($customer) {
            $this->updateCustomerPreference($customer, 'preferred_language', $language);
            $this->updateCustomerPreference($customer, 'locale', $language);
        }

        Session::put('locale', $language);

        // Set application locale
        app()->setLocale($language);

        // Clear cache
        $this->clearCache();

        $languagePreferenceChangedEvent = \App\Events\User\LanguagePreferenceChanged::class;
        if (class_exists($languagePreferenceChangedEvent)) {
            try {
                event(new $languagePreferenceChangedEvent($language));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * Get available currencies
     */
    public function getAvailableCurrencies(): array
    {
        return ['XOF'];
    }

    /**
     * Get available languages
     */
    public function getAvailableLanguages(): array
    {
        return config('localization.supported', [
            'en' => 'English',
            'fr' => 'Français',
        ]);
    }

    /**
     * Get currency conversion rates
     */
    public function getCurrencyRates(): array
    {
        return Cache::remember('currency.rates', 3600, function () {
            $rates = config('currency.rates', []);
            $baseCurrency = config('currency.base', 'USD');

            // Build comprehensive rates array for frontend
            $allRates = [];

            // Add base currency rate
            $allRates[$baseCurrency] = 1.0;

            foreach ($rates as $rateKey => $rateValue) {
                if (str_contains($rateKey, '_')) {
                    [$from, $to] = explode('_', $rateKey);

                    // Convert string rates to float
                    $rate = is_numeric($rateValue) ? (float) $rateValue : 1.0;

                    // Set up conversion rates for frontend
                    // The frontend expects rates in format: "USD_XOF" => rate
                    $allRates[$rateKey] = $rate;

                    // Also add individual currency rates for reference
                    if (!isset($allRates[$from])) {
                        $allRates[$from] = $from === $baseCurrency ? 1.0 : $rate;
                    }
                    if (!isset($allRates[$to])) {
                        $allRates[$to] = $to === $baseCurrency ? 1.0 : (1.0 / $rate);
                    }
                }
            }

            return $allRates;
        });
    }

    /**
     * Get currency decimal precision
     */
    public function getCurrencyDecimals(): array
    {
        return config('currency.decimals', [
            'USD' => 2,
            'EUR' => 2,
            'XOF' => 0,
            'XAF' => 0
        ]);
    }

    /**
     * Get display settings
     */
    public function getDisplaySettings(): array
    {
        return [
            'auto_convert_prices' => Cache::get('currency_auto_convert', true),
            'show_currency_selector' => false,
            'default_customer_currency' => 'XOF',
        ];
    }

    private function fetchPreferences(): array
    {
        $currency = 'XOF';
        $language = 'en';

        $customer = $this->resolveCustomer();
        if ($customer) {
            $currency = $customer->preferred_currency ?? $currency;
            $language = $customer->preferred_language ?? $customer->locale ?? $language;
        } else {
            $currency = Session::get('user_currency', $currency);
            $language = Session::get('locale', $language);
        }

        return [
            'currency' => 'XOF',
            'language' => $language,
            'available_currencies' => $this->getAvailableCurrencies(),
            'available_languages' => $this->getAvailableLanguages(),
            'currency_rates' => $this->getCurrencyRates(),
            'currency_decimals' => $this->getCurrencyDecimals(),
            'display_settings' => $this->getDisplaySettings(),
        ];
    }

    private function updateCustomerPreference(Customer $customer, string $field, string $value): void
    {
        if ($customer->getAttribute($field) === $value) {
            return;
        }

        $customer->setAttribute($field, $value);
        $customer->save();
    }

    private function validateCurrency(string $currency): void
    {
        if (!in_array($currency, $this->getAvailableCurrencies())) {
            throw new \InvalidArgumentException("Currency {$currency} is not supported");
        }
    }

    private function validateLanguage(string $language): void
    {
        if (!array_key_exists($language, $this->getAvailableLanguages())) {
            throw new \InvalidArgumentException("Language {$language} is not supported");
        }
    }

    private function getCacheKey(): string
    {
        $customer = $this->resolveCustomer();
        if ($customer) {
            return self::CACHE_PREFIX . '.user.' . $customer->getKey();
        }

        return self::CACHE_PREFIX . '.session.' . Session::getId();
    }

    private function resolveCustomer(): ?Customer
    {
        $customer = auth('customer')->user();
        if ($customer instanceof Customer) {
            return $customer;
        }

        $fallback = auth()->user();
        if ($fallback instanceof Customer) {
            return $fallback;
        }

        return null;
    }

    private function clearCache(): void
    {
        Cache::forget($this->getCacheKey());
    }
}
