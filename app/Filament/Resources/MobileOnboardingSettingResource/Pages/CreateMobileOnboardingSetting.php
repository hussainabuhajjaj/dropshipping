<?php

declare(strict_types=1);

namespace App\Filament\Resources\MobileOnboardingSettingResource\Pages;

use App\Filament\Resources\MobileOnboardingSettingResource;
use App\Services\AI\ContentTranslationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMobileOnboardingSetting extends CreateRecord
{
    protected static string $resource = MobileOnboardingSettingResource::class;

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
                    $rawSlides = $state['slides'] ?? [];
                    $slides = is_array($rawSlides) ? $rawSlides : [];

                    if ($slides === []) {
                        Notification::make()
                            ->warning()
                            ->title('Nothing to translate')
                            ->body('Add at least one slide, then try again.')
                            ->send();
                        return;
                    }

                    try {
                        $service = app(ContentTranslationService::class);
                        foreach ($slides as $index => $slide) {
                            if (! is_array($slide)) {
                                continue;
                            }

                            $title = is_string($slide['title'] ?? null) ? $slide['title'] : '';
                            $body = is_string($slide['body'] ?? null) ? $slide['body'] : '';

                            $translated = $service->translateFields([
                                'title' => $title,
                                'body' => $body,
                            ], 'en', 'fr');

                            if (isset($translated['title']) && is_string($translated['title']) && trim($translated['title']) !== '') {
                                $slide['title'] = $translated['title'];
                            }
                            if (isset($translated['body']) && is_string($translated['body']) && trim($translated['body']) !== '') {
                                $slide['body'] = $translated['body'];
                            }

                            $slides[$index] = $slide;
                        }
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Translation failed')
                            ->body($e->getMessage())
                            ->send();
                        return;
                    }

                    $state['locale'] = 'fr';
                    $state['slides'] = $slides;
                    $this->form->fill($state);

                    Notification::make()
                        ->success()
                        ->title('Translated to French')
                        ->send();
                }),
        ];
    }
}
