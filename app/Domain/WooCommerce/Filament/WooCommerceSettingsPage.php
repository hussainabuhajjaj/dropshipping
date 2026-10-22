<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Filament;

use App\Domain\WooCommerce\Contracts\WooCommerceClientContract;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WooCommerceSettingsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|\UnitEnum|null $navigationGroup = 'Integrations';

    protected static ?string $navigationLabel = 'WooCommerce';

    protected static ?string $title = 'WooCommerce Settings';

    protected string $view = 'filament.pages.woocommerce-settings';

    protected static ?int $navigationSort = 80;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'enabled' => config('woocommerce.enabled'),
            'base_url' => config('woocommerce.base_url'),
            'consumer_key' => config('woocommerce.consumer_key'),
            'consumer_secret' => config('woocommerce.consumer_secret'),
            'webhook_secret' => config('woocommerce.webhook_secret'),
            'timeout' => config('woocommerce.timeout'),
            'retry_times' => config('woocommerce.retry_times'),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Connection')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Enable WooCommerce Integration')
                            ->helperText('When disabled, no WooCommerce API calls will be made.'),

                        TextInput::make('base_url')
                            ->label('WooCommerce Base URL')
                            ->placeholder('https://your-store.com')
                            ->url()
                            ->required(),

                        TextInput::make('consumer_key')
                            ->label('Consumer Key')
                            ->required()
                            ->password()
                            ->revealable(),

                        TextInput::make('consumer_secret')
                            ->label('Consumer Secret')
                            ->required()
                            ->password()
                            ->revealable(),

                        TextInput::make('webhook_secret')
                            ->label('Webhook Secret')
                            ->password()
                            ->revealable(),
                    ]),

                Section::make('API Settings')
                    ->schema([
                        TextInput::make('timeout')
                            ->label('Timeout (seconds)')
                            ->numeric()
                            ->default(30),

                        TextInput::make('retry_times')
                            ->label('Retry Attempts')
                            ->numeric()
                            ->default(3),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $this->validate();

        $this->updateEnv('WC_ENABLED', $this->data['enabled'] ? 'true' : 'false');
        $this->updateEnv('WC_BASE_URL', $this->data['base_url']);
        $this->updateEnv('WC_CONSUMER_KEY', $this->data['consumer_key']);
        $this->updateEnv('WC_CONSUMER_SECRET', $this->data['consumer_secret']);
        $this->updateEnv('WC_WEBHOOK_SECRET', $this->data['webhook_secret']);
        $this->updateEnv('WC_TIMEOUT', (string) ($this->data['timeout'] ?? 30));
        $this->updateEnv('WC_RETRY_TIMES', (string) ($this->data['retry_times'] ?? 3));

        \Illuminate\Support\Facades\Artisan::call('config:clear');

        Notification::make()
            ->title('WooCommerce settings saved successfully')
            ->success()
            ->send();
    }

    public function testConnection(): void
    {
        try {
            $client = app(WooCommerceClientContract::class);

            if ($client->testConnection()) {
                Notification::make()
                    ->title('Connection successful')
                    ->body('Successfully connected to WooCommerce API.')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Connection failed')
                    ->body('Could not connect to WooCommerce. Please check your settings.')
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Connection failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function syncProducts(): void
    {
        \App\Domain\WooCommerce\Jobs\SyncWooProductsJob::dispatch();

        Notification::make()
            ->title('Product sync queued')
            ->body('All active products will be synced to WooCommerce.')
            ->success()
            ->send();
    }

    private function updateEnv(string $key, string $value): void
    {
        $path = base_path('.env');

        if (file_exists($path)) {
            $content = file_get_contents($path);

            if (str_contains($content, "{$key}=")) {
                $content = preg_replace(
                    "/^{$key}=.*/m",
                    "{$key}={$value}",
                    $content,
                );
            } else {
                $content .= "\n{$key}={$value}\n";
            }

            file_put_contents($path, $content);
        }
    }
}
