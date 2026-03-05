<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class CurrencySettings extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';
    protected static \UnitEnum|string|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 25;

    public function getTitle(): string
    {
        return 'Currency Settings';
    }

    public static function canView(): bool
    {
        $user = auth('admin')->user();
        return $user?->hasRole('admin') ?? false;
    }

    public function getView(): string
    {
        return 'filament.pages.currency-settings';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('save_settings')
                ->label('Save Settings')
                ->icon('heroicon-o-save')
                ->action('saveSettings')
                ->color('primary'),
            Actions\Action::make('test_conversion')
                ->label('Test Conversion')
                ->icon('heroicon-o-calculator')
                ->modalHeading('Test Currency Conversion')
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->default(100)
                        ->required(),
                    Forms\Components\Select::make('from_currency')
                        ->label('From Currency')
                        ->options([
                            'USD' => 'USD - US Dollar',
                            'EUR' => 'EUR - Euro',
                            'JOD' => 'JOD - Jordanian Dinar',
                            'XOF' => 'XOF - West African CFA Franc',
                            'XAF' => 'XAF - Central African CFA Franc',
                        ])
                        ->default('USD')
                        ->required(),
                    Forms\Components\Select::make('to_currency')
                        ->label('To Currency')
                        ->options([
                            'USD' => 'USD - US Dollar',
                            'EUR' => 'EUR - Euro',
                            'JOD' => 'JOD - Jordanian Dinar',
                            'XOF' => 'XOF - West African CFA Franc',
                            'XAF' => 'XAF - Central African CFA Franc',
                        ])
                        ->default('EUR')
                        ->required()
                        ->different('from_currency'),
                ])
                ->action('testConversion'),
        ];
    }

    public function saveSettings(array $data): void
    {
        // Update base currency
        if (isset($data['base_currency'])) {
            $this->updateEnvVariable('CURRENCY_BASE', $data['base_currency']);
        }

        // Update exchange rates
        if (isset($data['exchange_rates'])) {
            $rates = [];
            foreach ($data['exchange_rates'] as $rate) {
                if ($rate['active'] && isset($rate['from_currency'], $rate['to_currency'], $rate['rate'])) {
                    $key = $rate['from_currency'] . '_' . $rate['to_currency'];
                    $rates[$key] = $rate['rate'];
                }
            }
            $this->updateCurrencyRates($rates);
        }

        // Update decimal precision
        if (isset($data['decimals'])) {
            $this->updateCurrencyDecimals($data['decimals']);
        }

        // Update display settings
        if (isset($data['auto_convert_prices'])) {
            Cache::forever('currency_auto_convert', $data['auto_convert_prices']);
        }

        if (isset($data['show_currency_selector'])) {
            Cache::forever('currency_show_selector', $data['show_currency_selector']);
        }

        if (isset($data['default_customer_currency'])) {
            Cache::forever('currency_default_customer', $data['default_customer_currency']);
        }

        // Clear currency cache
        $this->clearCurrencyCache();

        Notification::make()
            ->title('Currency settings saved')
            ->body('All currency configurations have been updated successfully.')
            ->success()
            ->send();
    }

    public function testConversion(array $data): void
    {
        $amount = (float) $data['amount'];
        $from = $data['from_currency'];
        $to = $data['to_currency'];

        try {
            $converted = $this->convertAmount($amount, $from, $to);
            $decimals = config("currency.decimals.{$to}", 2);
            $formatted = number_format($converted, $decimals);

            Notification::make()
                ->title('Conversion Result')
                ->body("{$amount} {$from} = {$formatted} {$to}")
                ->info()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Conversion Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getFormData(): array
    {
        return [
            'base_currency' => config('currency.base', 'USD'),
            'exchange_rates' => $this->getCurrentRates(),
            'decimals' => config('currency.decimals', [
                'USD' => 2,
                'EUR' => 2,
                'JOD' => 3,
                'XOF' => 0,
                'XAF' => 0,
            ]),
            'auto_convert_prices' => Cache::get('currency_auto_convert', true),
            'show_currency_selector' => Cache::get('currency_show_selector', true),
            'default_customer_currency' => Cache::get('currency_default_customer', 'USD'),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Currency Configuration')
                ->description('Manage exchange rates and formatting settings for all supported currencies.')
                ->schema([
                    Forms\Components\Select::make('base_currency')
                        ->label('Base Currency')
                        ->options([
                            'USD' => 'USD - US Dollar',
                            'EUR' => 'EUR - Euro',
                            'JOD' => 'JOD - Jordanian Dinar',
                            'XOF' => 'XOF - West African CFA Franc',
                            'XAF' => 'XAF - Central African CFA Franc',
                        ])
                        ->default(config('currency.base', 'USD'))
                        ->required()
                        ->helperText('All prices are stored in this currency internally.'),

                    Forms\Components\Repeater::make('exchange_rates')
                        ->label('Exchange Rates')
                        ->schema([
                            Forms\Components\Select::make('from_currency')
                                ->label('From')
                                ->options([
                                    'USD' => 'USD - US Dollar',
                                    'EUR' => 'EUR - Euro',
                                    'JOD' => 'JOD - Jordanian Dinar',
                                    'XOF' => 'XOF - West African CFA Franc',
                                    'XAF' => 'XAF - Central African CFA Franc',
                                ])
                                ->required(),

                            Forms\Components\Select::make('to_currency')
                                ->label('To')
                                ->options([
                                    'USD' => 'USD - US Dollar',
                                    'EUR' => 'EUR - Euro',
                                    'JOD' => 'JOD - Jordanian Dinar',
                                    'XOF' => 'XOF - West African CFA Franc',
                                    'XAF' => 'XAF - Central African CFA Franc',
                                ])
                                ->required(),

                            Forms\Components\TextInput::make('rate')
                                ->label('Exchange Rate')
                                ->numeric()
                                ->step(0.0001)
                                ->required()
                                ->helperText('1 unit of From currency = X units of To currency')
                                ->suffix(fn ($record) => $record?->to_currency ?? ''),

                            Forms\Components\Toggle::make('active')
                                ->label('Active')
                                ->default(true),
                        ])
                        ->columns(4)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['from_currency'] . ' → ' . $state['to_currency'] ?? null)
                        ->addActionLabel('Add Exchange Rate')
                        ->reorderableWithButtons(false)
                        ->collapsible()
                        ->defaultItems(0),

                    Forms\Components\Section::make('Decimal Precision')
                        ->description('Set the number of decimal places for each currency.')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('decimals.USD')
                                        ->label('USD Decimals')
                                        ->numeric()
                                        ->default(2)
                                        ->minValue(0)
                                        ->maxValue(4)
                                        ->helperText('US Dollar decimal places'),

                                    Forms\Components\TextInput::make('decimals.EUR')
                                        ->label('EUR Decimals')
                                        ->numeric()
                                        ->default(2)
                                        ->minValue(0)
                                        ->maxValue(4)
                                        ->helperText('Euro decimal places'),

                                    Forms\Components\TextInput::make('decimals.JOD')
                                        ->label('JOD Decimals')
                                        ->numeric()
                                        ->default(3)
                                        ->minValue(0)
                                        ->maxValue(4)
                                        ->helperText('Jordanian Dinar decimal places'),

                                    Forms\Components\TextInput::make('decimals.XOF')
                                        ->label('XOF Decimals')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->maxValue(4)
                                        ->helperText('West African CFA Franc decimal places'),

                                    Forms\Components\TextInput::make('decimals.XAF')
                                        ->label('XAF Decimals')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->maxValue(4)
                                        ->helperText('Central African CFA Franc decimal places'),
                                ]),
                        ]),

                    Forms\Components\Section::make('Currency Display')
                        ->description('Control how currencies are displayed to customers.')
                        ->schema([
                            Forms\Components\Toggle::make('auto_convert_prices')
                                ->label('Auto-convert prices for customers')
                                ->default(true)
                                ->helperText('Automatically convert prices to customer\'s preferred currency'),

                            Forms\Components\Toggle::make('show_currency_selector')
                                ->label('Show currency selector')
                                ->default(true)
                                ->helperText('Display currency selector in header and checkout'),

                            Forms\Components\Select::make('default_customer_currency')
                                ->label('Default Customer Currency')
                                ->options([
                                    'USD' => 'USD - US Dollar',
                                    'EUR' => 'EUR - Euro',
                                    'JOD' => 'JOD - Jordanian Dinar',
                                    'XOF' => 'XOF - West African CFA Franc',
                                    'XAF' => 'XAF - Central African CFA Franc',
                                ])
                                ->default('USD')
                                ->helperText('Default currency for new customers'),
                        ]),
                ])
                ->columns(1),
        ];
    }

    private function updateEnvVariable(string $key, string $value): void
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        if (preg_match("/^{$key}=/m", $envContent)) {
            $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $envContent);
        } else {
            $envContent .= "\n{$key}={$value}";
        }

        file_put_contents($envPath, $envContent);
        Config::set('currency.base', $value);
    }

    private function updateCurrencyRates(array $rates): void
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        foreach ($rates as $key => $value) {
            $envKey = 'FX_' . strtoupper($key);

            if (preg_match("/^{$envKey}=/m", $envContent)) {
                $envContent = preg_replace("/^{$envKey}=.*/m", "{$envKey}={$value}", $envContent);
            } else {
                $envContent .= "\n{$envKey}={$value}";
            }

            Config::set("currency.rates.{$key}", $value);
        }

        file_put_contents($envPath, $envContent);
    }

    private function updateCurrencyDecimals(array $decimals): void
    {
        $configPath = config_path('currency.php');
        $configContent = file_get_contents($configPath);

        foreach ($decimals as $currency => $precision) {
            $pattern = "/'{$currency}'\s*=>\s*\d+/";
            $replacement = "'{$currency}' => {$precision}";

            if (preg_match($pattern, $configContent)) {
                $configContent = preg_replace($pattern, $replacement, $configContent);
            }

            Config::set("currency.decimals.{$currency}", (int) $precision);
        }

        file_put_contents($configPath, $configContent);
    }

    private function clearCurrencyCache(): void
    {
        Cache::forget('currency_rates');
        Cache::forget('exchange_rates');

        $currencies = ['USD', 'EUR', 'JOD', 'XOF', 'XAF'];
        foreach ($currencies as $from) {
            foreach ($currencies as $to) {
                Cache::forget("exchange_rate_{$from}_{$to}");
            }
        }
    }

    private function convertAmount(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return $amount;
        }

        $rates = config('currency.rates', []);
        $directKey = "{$from}_{$to}";
        $inverseKey = "{$to}_{$from}";

        if (isset($rates[$directKey]) && is_numeric($rates[$directKey])) {
            return $amount * (float) $rates[$directKey];
        }

        if (isset($rates[$inverseKey]) && is_numeric($rates[$inverseKey])) {
            return $amount / (float) $rates[$inverseKey];
        }

        throw new \RuntimeException("Exchange rate for {$from} → {$to} is not configured.");
    }

    private function getCurrentRates(): array
    {
        $rates = config('currency.rates', []);
        $formattedRates = [];

        foreach ($rates as $key => $value) {
            if (str_contains($key, '_') && is_numeric($value)) {
                [$from, $to] = explode('_', $key);
                $formattedRates[] = [
                    'from_currency' => $from,
                    'to_currency' => $to,
                    'rate' => (float) $value,
                    'active' => true,
                ];
            }
        }

        return $formattedRates;
    }
}
