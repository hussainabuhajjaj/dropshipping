<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Services\Currency\CurrencyConversionService;

trait AdminCurrencyConversion
{
    protected function currencyConversionFactorSql(string $currencyExpression, string $toCurrency = 'USD'): string
    {
        $converter = app(CurrencyConversionService::class);
        $supportedCurrencies = array_map('strtoupper', (array) config('currency.supported', ['USD', 'XOF']));
        $targetCurrency = strtoupper($toCurrency);
        $defaultCurrency = strtoupper(config('currency.base', 'USD'));

        $cases = [];
        foreach ($supportedCurrencies as $sourceCurrency) {
            $rate = $converter->rate($sourceCurrency, $targetCurrency);
            $cases[] = "WHEN '{$sourceCurrency}' THEN {$rate}";
        }

        return "(CASE UPPER(TRIM(COALESCE({$currencyExpression}, '{$defaultCurrency}'))) " . implode(' ', $cases) . " ELSE 1 END)";
    }

    protected function amountToUsdSql(string $amountExpression, string $currencyExpression): string
    {
        return "COALESCE({$amountExpression}, 0) * {$this->currencyConversionFactorSql($currencyExpression, 'USD')}";
    }
}
