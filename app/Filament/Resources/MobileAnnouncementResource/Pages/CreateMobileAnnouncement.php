<?php

declare(strict_types=1);

namespace App\Filament\Resources\MobileAnnouncementResource\Pages;

use App\Filament\Resources\MobileAnnouncementResource;
use App\Services\AI\ContentTranslationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMobileAnnouncement extends CreateRecord
{
    protected static string $resource = MobileAnnouncementResource::class;

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
                    $title = is_string($state['title'] ?? null) ? $state['title'] : '';
                    $body = is_string($state['body'] ?? null) ? $state['body'] : '';

                    if (trim($title) === '' && trim($body) === '') {
                        Notification::make()
                            ->warning()
                            ->title('Nothing to translate')
                            ->body('Fill title or body, then try again.')
                            ->send();
                        return;
                    }

                    try {
                        $service = app(ContentTranslationService::class);
                        $translated = $service->translateFields([
                            'title' => $title,
                            'body' => $body,
                        ], 'en', 'fr');
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Translation failed')
                            ->body($e->getMessage())
                            ->send();
                        return;
                    }

                    $state['locale'] = 'fr';
                    if (isset($translated['title']) && is_string($translated['title']) && trim($translated['title']) !== '') {
                        $state['title'] = $translated['title'];
                    }
                    if (isset($translated['body']) && is_string($translated['body']) && trim($translated['body']) !== '') {
                        $state['body'] = $translated['body'];
                    }

                    $this->form->fill($state);

                    Notification::make()
                        ->success()
                        ->title('Translated to French')
                        ->send();
                }),
        ];
    }
}
