<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportConversationResource\RelationManagers;

use App\Domain\Support\Models\SupportConversation;
use App\Domain\Support\Services\SupportChatService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Live Chat';

    protected static ?string $modelLabel = 'Message';

    protected function getListeners(): array
    {
        return [
            ...parent::getListeners(),
            'echo-private:support.admin,support.message.created' => 'handleSupportMessageCreated',
        ];
    }

    public function handleSupportMessageCreated(array $payload = []): void
    {
        $conversation = $this->getOwnerRecord();
        if (! $conversation instanceof SupportConversation) {
            return;
        }

        $eventConversationUuid = (string) data_get($payload, 'conversation_uuid', '');
        if ($eventConversationUuid === '' || $eventConversationUuid !== (string) $conversation->uuid) {
            return;
        }

        if ((string) data_get($payload, 'message.sender_type', '') === 'customer') {
            app(SupportChatService::class)->markMessagesReadByAdmin($conversation);
        }

        $this->flushCachedTableRecords();
    }

    public function table(Table $table): Table
    {
        $conversation = $this->getOwnerRecord();
        if ($conversation instanceof SupportConversation) {
            app(SupportChatService::class)->markMessagesReadByAdmin($conversation);
        }

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['senderUser:id,name', 'senderCustomer:id,first_name,last_name']))
            ->poll('8s')
            ->columns([
                Tables\Columns\ViewColumn::make('chat_message')
                    ->label('')
                    ->view('filament.support.messages.chat-bubble'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_internal_note')
                    ->label('Internal notes'),
            ])
            ->headerActions([
                Action::make('reply')
                    ->label('Send message')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->slideOver()
                    ->modalHeading('Send reply')
                    ->modalDescription('Reply to customer or add an internal note.')
                    ->modalWidth('3xl')
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->form([
                        Forms\Components\Textarea::make('body')
                            ->label('Message')
                            ->rows(8)
                            ->required()
                            ->maxLength(4000),
                        Forms\Components\Toggle::make('internal_note')
                            ->label('Internal note (not visible to customer)')
                            ->default(false),
                    ])
                    ->action(function (array $data): void {
                        $conversation = $this->getOwnerRecord();
                        if (! $conversation instanceof SupportConversation) {
                            return;
                        }

                        $user = auth(config('filament.auth.guard', 'admin'))->user();
                        if (! $user) {
                            return;
                        }

                        app(SupportChatService::class)->addAdminReply(
                            $conversation,
                            $user,
                            (string) $data['body'],
                            (bool) ($data['internal_note'] ?? false)
                        );

                        Notification::make()
                            ->success()
                            ->title((bool) ($data['internal_note'] ?? false) ? 'Internal note added' : 'Reply sent')
                            ->send();
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->recordClasses(fn () => 'align-top !border-0')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->defaultSort('id', 'asc')
            ->emptyStateHeading('No messages yet')
            ->emptyStateDescription('This thread is ready for the first support reply.');
    }
}
