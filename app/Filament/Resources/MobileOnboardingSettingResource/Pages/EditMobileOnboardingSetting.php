<?php

declare(strict_types=1);

namespace App\Filament\Resources\MobileOnboardingSettingResource\Pages;

use App\Filament\Resources\MobileOnboardingSettingResource;
use App\Models\MobileOnboardingSetting;
use App\Services\AI\ContentTranslationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMobileOnboardingSetting extends EditRecord
{
    protected static string $resource = MobileOnboardingSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_fr_translation')
                ->label('Sync FR translation')
                ->icon('heroicon-o-language')
                ->requiresConfirmation()
                ->visible(fn (): bool => ($this->record?->locale ?? 'en') === 'en')
                ->action(function (): void {
                    /** @var MobileOnboardingSetting $record */
                    $record = $this->record;

                    if (empty(config('services.deepseek.key'))) {
                        Notification::make()
                            ->danger()
                            ->title('DeepSeek not configured')
                            ->body('Set DEEPSEEK_API_KEY in your .env to enable translations.')
                            ->send();
                        return;
                    }

                    $slides = is_array($record->slides) ? $record->slides : [];

                    if ($slides === []) {
                        Notification::make()
                            ->warning()
                            ->title('Nothing to translate')
                            ->body('This onboarding has no slides.')
                            ->send();
                        return;
                    }

                    try {
                        $service = app(ContentTranslationService::class);
                        $translatedSlides = [];

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

                            $translatedSlides[] = $slide;
                        }
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Translation failed')
                            ->body($e->getMessage())
                            ->send();
                        return;
                    }

                    MobileOnboardingSetting::updateOrCreate(
                        ['locale' => 'fr'],
                        [
                            'enabled' => (bool) $record->enabled,
                            'slides' => $translatedSlides,
                        ]
                    );

                    Notification::make()
                        ->success()
                        ->title('French onboarding synced')
                        ->body('Open the FR onboarding record to review it.')
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
