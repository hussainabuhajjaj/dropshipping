<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontSettingResource\Pages;

use App\Filament\Resources\StorefrontSettingResource;
use App\Models\StorefrontSetting;
use App\Services\AI\StorefrontSettingTranslationService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStorefrontSetting extends EditRecord
{
    protected static string $resource = StorefrontSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_fr_translation')
                ->label('Sync FR translation')
                ->icon('heroicon-o-language')
                ->requiresConfirmation()
                ->visible(fn (): bool => (($this->record?->locale ?? null) !== 'fr'))
                ->schema([
                    Forms\Components\Toggle::make('overwrite')
                        ->label('Overwrite existing FR record')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    if (empty(config('services.deepseek.key'))) {
                        Notification::make()
                            ->danger()
                            ->title('DeepSeek not configured')
                            ->body('Set DEEPSEEK_API_KEY in your .env to enable translations.')
                            ->send();
                        return;
                    }

                    $overwrite = (bool) ($data['overwrite'] ?? true);
                    $state = $this->form->getState();

                    try {
                        $translator = app(StorefrontSettingTranslationService::class);
                        $translated = $translator->translateState($state, 'en', 'fr');
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Translation failed')
                            ->body($e->getMessage())
                            ->send();
                        return;
                    }

                    $payload = [
                        'locale' => 'fr',
                        'brand_name' => $translated['brand_name'] ?? null,
                        'footer_blurb' => $translated['footer_blurb'] ?? null,
                        'delivery_notice' => $translated['delivery_notice'] ?? null,
                        'copyright_text' => $translated['copyright_text'] ?? null,
                        'header_links' => $translated['header_links'] ?? null,
                        'footer_columns' => $translated['footer_columns'] ?? null,
                        'value_props' => $translated['value_props'] ?? null,
                        'social_links' => $translated['social_links'] ?? null,
                        'coming_soon_enabled' => $translated['coming_soon_enabled'] ?? false,
                        'coming_soon_title' => $translated['coming_soon_title'] ?? null,
                        'coming_soon_message' => $translated['coming_soon_message'] ?? null,
                        'coming_soon_image' => $translated['coming_soon_image'] ?? null,
                        'coming_soon_cta_label' => $translated['coming_soon_cta_label'] ?? null,
                        'coming_soon_cta_url' => $translated['coming_soon_cta_url'] ?? null,
                        'newsletter_popup_enabled' => $translated['newsletter_popup_enabled'] ?? false,
                        'newsletter_popup_title' => $translated['newsletter_popup_title'] ?? null,
                        'newsletter_popup_body' => $translated['newsletter_popup_body'] ?? null,
                        'newsletter_popup_incentive' => $translated['newsletter_popup_incentive'] ?? null,
                        'newsletter_popup_image' => $translated['newsletter_popup_image'] ?? null,
                        'newsletter_popup_delay_seconds' => $translated['newsletter_popup_delay_seconds'] ?? 3,
                        'newsletter_popup_dismiss_days' => $translated['newsletter_popup_dismiss_days'] ?? 14,
                    ];

                    $fr = StorefrontSetting::query()
                        ->where('locale', 'fr')
                        ->latest()
                        ->first();

                    if ($fr) {
                        if (! $overwrite) {
                            foreach ([
                                'footer_blurb',
                                'delivery_notice',
                                'header_links',
                                'footer_columns',
                                'value_props',
                                'social_links',
                                'coming_soon_title',
                                'coming_soon_message',
                                'coming_soon_cta_label',
                                'newsletter_popup_title',
                                'newsletter_popup_body',
                                'newsletter_popup_incentive',
                            ] as $field) {
                                if ($this->isFilled($fr->{$field} ?? null)) {
                                    unset($payload[$field]);
                                }
                            }
                        }

                        $fr->update($payload);
                    } else {
                        StorefrontSetting::create($payload);
                    }

                    Notification::make()
                        ->success()
                        ->title('French storefront settings synced')
                        ->body('Switch locale to FR in the storefront to verify.')
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    private function isFilled(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return count($value) > 0;
        }

        return $value !== null;
    }
}
