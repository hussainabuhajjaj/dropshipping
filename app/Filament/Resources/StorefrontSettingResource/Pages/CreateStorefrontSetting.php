<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontSettingResource\Pages;

use App\Filament\Resources\StorefrontSettingResource;
use App\Services\AI\StorefrontSettingTranslationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateStorefrontSetting extends CreateRecord
{
    protected static string $resource = StorefrontSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('translate_to_fr')
                ->label('Translate EN → FR')
                ->icon('heroicon-o-language')
                ->action(function (): void {
                    if (empty(config('services.deepseek.key'))) {
                        Notification::make()
                            ->danger()
                            ->title('DeepSeek not configured')
                            ->body('Set DEEPSEEK_API_KEY in your .env to enable translations.')
                            ->send();
                        return;
                    }

                    $state = $this->form->getState();

                    try {
                        $translator = app(StorefrontSettingTranslationService::class);
                        $state = $translator->translateState($state, 'en', 'fr');
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Translation failed')
                            ->body($e->getMessage())
                            ->send();
                        return;
                    }

                    $state['locale'] = 'fr';
                    $this->form->fill($state);

                    Notification::make()
                        ->success()
                        ->title('Translated to French')
                        ->send();
                }),
        ];
    }
}
