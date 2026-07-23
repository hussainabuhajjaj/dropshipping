<?php

declare(strict_types=1);

namespace App\Contracts\User;

interface PreferenceContract
{
    /** Get all user preferences including available currencies, languages, rates, and display settings. */
    public function getPreferences(): array;

    /** Set the user's preferred currency. */
    public function setCurrency(string $currency): void;

    /** Set the user's preferred language. */
    public function setLanguage(string $language): void;

    /** Get available currencies. */
    public function getAvailableCurrencies(): array;

    /** Get available languages. */
    public function getAvailableLanguages(): array;
}
