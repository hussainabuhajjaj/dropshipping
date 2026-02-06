<?php

declare(strict_types=1);

namespace App\Filament\Resources\MobileAnnouncementResource\Pages;

use App\Filament\Resources\MobileAnnouncementResource;
use App\Models\MobileAnnouncement;
use App\Services\AI\ContentTranslationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMobileAnnouncement extends EditRecord
{
    protected static string $resource = MobileAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_fr_translation')
                ->label('Create FR translation')
                ->icon('heroicon-o-language')
                ->requiresConfirmation()
                ->visible(fn (): bool => ($this->record?->locale ?? 'en') === 'en')
                ->action(function (): void {
                    /** @var MobileAnnouncement $record */
                    $record = $this->record;

                    if (empty(config('services.deepseek.key'))) {
                        Notification::make()
                            ->danger()
                            ->title('DeepSeek not configured')
                            ->body('Set DEEPSEEK_API_KEY in your .env to enable translations.')
                            ->send();
                        return;
                    }

                    try {
                        $service = app(ContentTranslationService::class);
                        $translated = $service->translateFields([
                            'title' => (string) $record->title,
                            'body' => (string) $record->body,
                        ], 'en', 'fr');
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Translation failed')
                            ->body($e->getMessage())
                            ->send();
                        return;
                    }

                    MobileAnnouncement::create([
                        'locale' => 'fr',
                        'enabled' => (bool) $record->enabled,
                        'title' => (string) ($translated['title'] ?? $record->title),
                        'body' => (string) ($translated['body'] ?? $record->body),
                        'image' => $record->image,
                        'action_href' => $record->action_href,
                        'send_database' => (bool) $record->send_database,
                        'send_push' => (bool) $record->send_push,
                        'send_email' => (bool) $record->send_email,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('French announcement created')
                        ->body('Open it from the announcements list to review and send.')
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
