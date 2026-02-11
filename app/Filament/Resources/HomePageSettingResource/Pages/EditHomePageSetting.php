<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomePageSettingResource\Pages;

use App\Filament\Resources\HomePageSettingResource;
use App\Models\HomePageSetting;
use App\Services\AI\HomePageSettingTranslationService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditHomePageSetting extends EditRecord
{
    protected static string $resource = HomePageSettingResource::class;

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
                        $translator = app(HomePageSettingTranslationService::class);
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
                        'top_strip' => $translated['top_strip'] ?? [],
                        'hero_slides' => $translated['hero_slides'] ?? [],
                        'rail_cards' => $translated['rail_cards'] ?? [],
                        'category_highlights' => $state['category_highlights'] ?? [],
                        'banner_strip' => $translated['banner_strip'] ?? [],
                    ];

                    $fr = HomePageSetting::query()
                        ->where('locale', 'fr')
                        ->latest()
                        ->first();

                    if ($fr) {
                        if (! $overwrite) {
                            foreach (['top_strip', 'hero_slides', 'rail_cards', 'category_highlights', 'banner_strip'] as $field) {
                                if ($this->isFilled($fr->{$field} ?? null)) {
                                    unset($payload[$field]);
                                }
                            }
                        }

                        $fr->update($payload);
                    } else {
                        HomePageSetting::create($payload);
                    }

                    Notification::make()
                        ->success()
                        ->title('French home content synced')
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
