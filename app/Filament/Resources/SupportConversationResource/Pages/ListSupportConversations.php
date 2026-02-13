<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportConversationResource\Pages;

use App\Filament\Resources\SupportConversationResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListSupportConversations extends ListRecords
{
    protected static string $resource = SupportConversationResource::class;

    protected function getListeners(): array
    {
        return [
            ...parent::getListeners(),
            'echo-private:support.admin,support.message.created' => 'handleSupportMessageCreated',
        ];
    }

    public function handleSupportMessageCreated(array $payload = []): void
    {
        $conversationUuid = (string) data_get($payload, 'conversation_uuid', '');
        if ($conversationUuid === '') {
            return;
        }

        $this->flushCachedTableRecords();

        if ((string) data_get($payload, 'message.sender_type', '') !== 'customer') {
            return;
        }

        $messageBody = trim((string) data_get($payload, 'message.body', ''));

        Notification::make()
            ->title('New customer message')
            ->body($messageBody !== '' ? Str::limit($messageBody, 120) : 'A conversation has a new message.')
            ->info()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        $widgets = [];

        $statsWidget = \App\Filament\Resources\SupportConversationResource\Widgets\SupportConversationStatsWidget::class;
        if (class_exists($statsWidget)) {
            $widgets[] = $statsWidget;
        }

        return $widgets;
    }
}
