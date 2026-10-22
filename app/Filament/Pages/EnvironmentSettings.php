<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\EnvChangeLog;
use App\Services\EnvEditor;
use BackedEnum;
use Filament\Notifications\Notification;
use App\Filament\Pages\BasePage;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

class EnvironmentSettings extends BasePage
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static UnitEnum|string|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 97;
    protected static bool $adminOnly = true;

    protected string $view = 'filament.pages.environment-settings';

    public array $values = [];
    public array $groups = [];
    public array $existingKeys = [];

    public function mount(EnvEditor $editor): void
    {
        $this->groups = $this->buildGroups();
        $all = $editor->all();
        $this->existingKeys = array_fill_keys(array_keys($all), true);

        foreach ($this->flattenFields() as $key => $field) {
            $value = $all[$key] ?? null;
            if ($value === null) {
                $value = $field['default'] ?? '';
            }

            $this->values[$key] = $value;
        }
    }

    public function save(EnvEditor $editor): void
    {
        $this->validate($this->buildRules());

        $before = $editor->all();
        $payload = [];
        foreach ($this->flattenFields() as $key => $field) {
            $value = $this->values[$key] ?? null;

            if (isset($this->existingKeys[$key]) || ($value !== null && $value !== '')) {
                $payload[$key] = $value;
            }
        }

        try {
            $editor->setMany($payload);
            $changes = $this->diffChanges($before, $payload);

            if (! empty($changes)) {
                EnvChangeLog::create([
                    'user_id' => auth(config('filament.auth.guard', 'admin'))->id(),
                    'changes' => $changes,
                    'ip_address' => request()->ip(),
                    'user_agent' => substr((string) request()->userAgent(), 0, 500),
                ]);
            }

            Notification::make()
                ->title('Environment updated')
                ->body('Clear the config cache if changes do not apply immediately.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Update failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveAndClearCache(EnvEditor $editor): void
    {
        $this->save($editor);
        $this->clearConfigCache();
    }

    public function clearConfigCache(): void
    {
        try {
            Artisan::call('config:clear');

            Notification::make()
                ->title('Config cache cleared')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Config cache clear failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function buildGroups(): array
    {
        return [
            'Application' => [
                'description' => 'Core app settings used across the storefront and admin.',
                'fields' => [
                    'APP_NAME' => [
                        'label' => 'App name',
                        'type' => 'text',
                        'rules' => ['nullable', 'string', 'max:120'],
                    ],
                    'APP_URL' => [
                        'label' => 'App URL',
                        'type' => 'url',
                        'rules' => ['nullable', 'url', 'max:200'],
                    ],
                    'APP_ENV' => [
                        'label' => 'Environment',
                        'type' => 'select',
                        'options' => [
                            'local' => 'Local',
                            'staging' => 'Staging',
                            'production' => 'Production',
                            'testing' => 'Testing',
                        ],
                        'rules' => ['nullable', 'in:local,staging,production,testing'],
                    ],
                    'APP_DEBUG' => [
                        'label' => 'Debug mode',
                        'type' => 'select',
                        'options' => [
                            'true' => 'True',
                            'false' => 'False',
                        ],
                        'rules' => ['nullable', 'in:true,false'],
                        'help' => 'Set false on production.',
                    ],
                    'APP_TIMEZONE' => [
                        'label' => 'Timezone',
                        'type' => 'text',
                        'default' => 'UTC',
                        'rules' => ['nullable', 'string', 'max:60'],
                    ],
                    'APP_LOCALE' => [
                        'label' => 'Locale',
                        'type' => 'text',
                        'default' => 'en',
                        'rules' => ['nullable', 'string', 'max:10'],
                    ],
                    'APP_FALLBACK_LOCALE' => [
                        'label' => 'Fallback locale',
                        'type' => 'text',
                        'default' => 'en',
                        'rules' => ['nullable', 'string', 'max:10'],
                    ],
                ],
            ],
            'Mail' => [
                'description' => 'Outgoing email configuration.',
                'fields' => [
                    'MAIL_MAILER' => [
                        'label' => 'Mailer',
                        'type' => 'select',
                        'options' => [
                            'log' => 'Log',
                            'smtp' => 'SMTP',
                            'ses' => 'SES',
                            'postmark' => 'Postmark',
                            'resend' => 'Resend',
                            'mailgun' => 'Mailgun',
                            'sendmail' => 'Sendmail',
                        ],
                        'rules' => ['nullable', 'string', 'max:40'],
                    ],
                    'MAIL_HOST' => [
                        'label' => 'Host',
                        'type' => 'text',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'MAIL_PORT' => [
                        'label' => 'Port',
                        'type' => 'number',
                        'rules' => ['nullable', 'integer', 'min:1', 'max:65535'],
                    ],
                    'MAIL_USERNAME' => [
                        'label' => 'Username',
                        'type' => 'text',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'MAIL_PASSWORD' => [
                        'label' => 'Password',
                        'type' => 'password',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'MAIL_FROM_ADDRESS' => [
                        'label' => 'From address',
                        'type' => 'email',
                        'rules' => ['nullable', 'email', 'max:200'],
                    ],
                    'MAIL_FROM_NAME' => [
                        'label' => 'From name',
                        'type' => 'text',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                ],
            ],
            'Webhooks' => [
                'description' => 'Shared secrets for incoming webhooks.',
                'fields' => [
                    'PAYMENTS_WEBHOOK_SECRET' => [
                        'label' => 'Payments webhook secret',
                        'type' => 'password',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'TRACKING_WEBHOOK_SECRET' => [
                        'label' => 'Tracking webhook secret',
                        'type' => 'password',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                ],
            ],
            'CJ Dropshipping' => [
                'description' => 'CJ API credentials and defaults.',
                'fields' => [
                    'CJ_APP_ID' => [
                        'label' => 'App ID',
                        'type' => 'text',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'CJ_API_SECRET' => [
                        'label' => 'API secret',
                        'type' => 'password',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'CJ_API_KEY' => [
                        'label' => 'API key',
                        'type' => 'password',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'CJ_BASE_URL' => [
                        'label' => 'Base URL',
                        'type' => 'url',
                        'default' => 'https://developers.cjdropshipping.com/api2.0',
                        'rules' => ['nullable', 'url', 'max:200'],
                    ],
                    'CJ_TIMEOUT' => [
                        'label' => 'Timeout (seconds)',
                        'type' => 'number',
                        'rules' => ['nullable', 'integer', 'min:1', 'max:120'],
                    ],
                    'CJ_WEBHOOK_SECRET' => [
                        'label' => 'Webhook secret',
                        'type' => 'password',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'CJ_PLATFORM_TOKEN' => [
                        'label' => 'Platform token',
                        'type' => 'password',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                ],
            ],
            'WhatsApp' => [
                'description' => 'Provider credentials for WhatsApp notifications.',
                'fields' => [
                    'WHATSAPP_PROVIDER' => [
                        'label' => 'Provider',
                        'type' => 'select',
                        'options' => [
                            'meta' => 'Meta Cloud API',
                            'twilio' => 'Twilio',
                            'vonage' => 'Vonage',
                        ],
                        'rules' => ['nullable', 'in:meta,twilio,vonage'],
                    ],
                    'WHATSAPP_META_TOKEN' => [
                        'label' => 'Meta token',
                        'type' => 'password',
                        'rules' => ['nullable', 'string', 'max:500'],
                    ],
                    'WHATSAPP_META_PHONE_NUMBER_ID' => [
                        'label' => 'Meta phone number ID',
                        'type' => 'text',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'WHATSAPP_META_API_VERSION' => [
                        'label' => 'Meta API version',
                        'type' => 'text',
                        'default' => 'v19.0',
                        'rules' => ['nullable', 'string', 'max:20'],
                    ],
                    'WHATSAPP_META_BASE_URL' => [
                        'label' => 'Meta base URL',
                        'type' => 'url',
                        'default' => 'https://graph.facebook.com',
                        'rules' => ['nullable', 'url', 'max:200'],
                    ],
                    'TWILIO_SID' => [
                        'label' => 'Twilio SID',
                        'type' => 'text',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'TWILIO_TOKEN' => [
                        'label' => 'Twilio token',
                        'type' => 'password',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'TWILIO_WHATSAPP_FROM' => [
                        'label' => 'Twilio WhatsApp from',
                        'type' => 'text',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'VONAGE_JWT' => [
                        'label' => 'Vonage JWT',
                        'type' => 'password',
                        'rules' => ['nullable', 'string', 'max:500'],
                    ],
                    'VONAGE_WHATSAPP_FROM' => [
                        'label' => 'Vonage WhatsApp from',
                        'type' => 'text',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'VONAGE_WHATSAPP_ENDPOINT' => [
                        'label' => 'Vonage endpoint',
                        'type' => 'url',
                        'default' => 'https://api.nexmo.com/v1/messages',
                        'rules' => ['nullable', 'url', 'max:200'],
                    ],
                ],
            ],
        ];
    }

    private function buildRules(): array
    {
        $rules = [];

        foreach ($this->flattenFields() as $key => $field) {
            $rules['values.' . $key] = $field['rules'] ?? ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }

    private function flattenFields(): array
    {
        $fields = [];

        foreach ($this->groups as $group) {
            foreach ($group['fields'] as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param array<string, string> $before
     * @param array<string, string|int|float|bool|null> $after
     * @return array<string, array{old:string,new:string}>
     */
    private function diffChanges(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $key => $value) {
            $old = $before[$key] ?? null;
            $new = $value === null ? '' : (string) $value;

            if ($this->normalizeValue($old) === $this->normalizeValue($new)) {
                continue;
            }

            $changes[$key] = [
                'old' => $this->maskValue($key, $old),
                'new' => $this->maskValue($key, $new),
            ];
        }

        return $changes;
    }

    private function normalizeValue(?string $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function maskValue(string $key, ?string $value): string
    {
        $value = (string) ($value ?? '');
        if ($value === '') {
            return '';
        }

        if (preg_match('/(key|secret|token|password|jwt)/i', $key) === 1) {
            return str_repeat('*', min(strlen($value), 10));
        }

        return $value;
    }
}

