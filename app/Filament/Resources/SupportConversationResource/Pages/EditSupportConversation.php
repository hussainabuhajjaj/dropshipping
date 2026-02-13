<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportConversationResource\Pages;

use App\Domain\Support\Models\SupportConversation;
use App\Domain\Support\Services\SupportChatService;
use App\Filament\Resources\SupportConversationResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSupportConversation extends EditRecord
{
    protected static string $resource = SupportConversationResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record instanceof SupportConversation) {
            app(SupportChatService::class)->markMessagesReadByAdmin($this->record);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mark_resolved')
                ->label('Mark resolved')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => ! in_array($this->record->status, ['resolved', 'closed'], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    if (! $this->record instanceof SupportConversation) {
                        return;
                    }

                    app(SupportChatService::class)->markResolved($this->record);

                    Notification::make()
                        ->success()
                        ->title('Conversation marked as resolved')
                        ->send();
                }),
        ];
    }
}
