<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportConversationResource\Pages;

use App\Domain\Support\Models\SupportConversation;
use App\Domain\Support\Services\SupportChatService;
use App\Filament\Resources\SupportConversationResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditSupportConversation extends EditRecord
{
    protected static string $resource = SupportConversationResource::class;

    public function getTitle(): string
    {
        if (! $this->record instanceof SupportConversation) {
            return 'Support chat';
        }

        return 'Support chat #' . $this->record->id;
    }

    public function getSubheading(): ?string
    {
        if (! $this->record instanceof SupportConversation) {
            return null;
        }

        $customerName = trim((string) $this->record->customer?->name);
        $status = Str::headline((string) $this->record->status);
        $priority = Str::headline((string) $this->record->priority);
        $agentMode = (string) $this->record->active_agent === 'human' ? 'Human agent' : 'AI assistant';

        return implode(' · ', array_filter([
            $customerName !== '' ? $customerName : 'Guest customer',
            $status,
            $priority,
            $agentMode,
        ]));
    }

    protected function getListeners(): array
    {
        return [
            ...parent::getListeners(),
            'echo-private:support.admin,support.message.created' => 'handleSupportMessageCreated',
        ];
    }

    public function handleSupportMessageCreated(array $payload = []): void
    {
        if (! $this->record instanceof SupportConversation) {
            return;
        }

        $eventConversationUuid = (string) data_get($payload, 'conversation_uuid', '');
        if ($eventConversationUuid === '' || $eventConversationUuid !== (string) $this->record->uuid) {
            return;
        }

        $this->record->refresh();
    }

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
