<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportConversationResource\RelationManagers;

use App\Domain\Support\Models\SupportConversation;
use App\Domain\Support\Services\SupportChatService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sender_type')
                    ->label('Sender')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'customer' => 'gray',
                        'agent' => 'success',
                        'ai' => 'primary',
                        'system' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('body')
                    ->wrap()
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_internal_note')
                    ->label('Internal')
                    ->boolean(),
                Tables\Columns\TextColumn::make('senderUser.name')
                    ->label('Admin')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_internal_note')
                    ->label('Internal notes'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('reply')
                    ->label('Reply')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->form([
                        Forms\Components\Textarea::make('body')
                            ->label('Message')
                            ->rows(4)
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
            ->defaultSort('id', 'desc');
    }
}
