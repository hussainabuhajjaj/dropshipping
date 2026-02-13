<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Support\Models\SupportConversation;
use App\Domain\Support\Services\SupportChatService;
use App\Filament\Resources\SupportConversationResource\Pages;
use App\Filament\Resources\SupportConversationResource\RelationManagers\MessagesRelationManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class SupportConversationResource extends BaseResource
{
    protected static ?string $model = SupportConversation::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static UnitEnum|string|null $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->where(function (Builder $query): void {
                $query
                    ->where('status', 'pending_agent')
                    ->orWhere(function (Builder $inner): void {
                        $inner->where('handoff_requested', true)->whereIn('status', ['open', 'pending_agent']);
                    });
            })
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Conversation')
                ->schema([
                    Forms\Components\TextInput::make('uuid')
                        ->disabled(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'open' => 'Open',
                            'pending_agent' => 'Pending Agent',
                            'pending_customer' => 'Pending Customer',
                            'resolved' => 'Resolved',
                            'closed' => 'Closed',
                        ])
                        ->native(false)
                        ->required(),
                    Forms\Components\Select::make('active_agent')
                        ->label('Active agent')
                        ->options([
                            'ai' => 'AI',
                            'human' => 'Human',
                        ])
                        ->native(false),
                    Forms\Components\Toggle::make('handoff_requested')
                        ->label('Handoff requested'),
                    Forms\Components\Toggle::make('ai_enabled')
                        ->label('AI enabled'),
                    Forms\Components\Select::make('assigned_user_id')
                        ->label('Assigned admin')
                        ->relationship(
                            name: 'assignedUser',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query
                                ->whereIn('role', ['admin', 'staff'])
                                ->where('is_active', true)
                        )
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('topic')
                        ->maxLength(120),
                    Forms\Components\Select::make('priority')
                        ->options([
                            'low' => 'Low',
                            'normal' => 'Normal',
                            'high' => 'High',
                            'urgent' => 'Urgent',
                        ])
                        ->native(false)
                        ->required(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
                'messages as unread_for_admin_count' => fn (Builder $inner): Builder => $inner
                    ->where('is_internal_note', false)
                    ->where('sender_type', 'customer')
                    ->whereNull('read_at'),
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(['customers.first_name', 'customers.last_name', 'customers.email'])
                    ->placeholder('Guest'),
                Tables\Columns\TextColumn::make('customer.email')
                    ->label('Email')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'info',
                        'pending_agent' => 'warning',
                        'pending_customer' => 'success',
                        'resolved' => 'gray',
                        'closed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('active_agent')
                    ->label('Agent')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'human' ? 'warning' : 'primary')
                    ->formatStateUsing(fn (?string $state): string => $state === 'human' ? 'Human' : 'AI'),
                Tables\Columns\IconColumn::make('handoff_requested')
                    ->label('Handoff')
                    ->boolean(),
                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned')
                    ->placeholder('Unassigned')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('unread_for_admin_count')
                    ->label('Unread')
                    ->badge()
                    ->color(fn (int|string|null $state): string => ((int) $state) > 0 ? 'danger' : 'gray')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->since()
                    ->sortable()
                    ->label('Last message'),
                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('mine')
                    ->label('My assigned')
                    ->default(fn (): bool => auth(config('filament.auth.guard', 'admin'))->user()?->role === 'staff')
                    ->query(function (Builder $query): Builder {
                        $userId = auth(config('filament.auth.guard', 'admin'))->id();
                        if (! $userId) {
                            return $query;
                        }

                        return $query->where('assigned_user_id', $userId);
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'pending_agent' => 'Pending Agent',
                        'pending_customer' => 'Pending Customer',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ]),
                Tables\Filters\SelectFilter::make('active_agent')
                    ->label('Agent')
                    ->options([
                        'ai' => 'AI',
                        'human' => 'Human',
                    ]),
                Tables\Filters\TernaryFilter::make('handoff_requested')
                    ->label('Handoff requested'),
                Tables\Filters\Filter::make('unassigned')
                    ->label('Unassigned')
                    ->query(fn (Builder $query): Builder => $query->whereNull('assigned_user_id')),
            ])
            ->recordActions([
                Action::make('assign_to_me')
                    ->label('Assign to me')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->visible(function (SupportConversation $record): bool {
                        $adminId = auth(config('filament.auth.guard', 'admin'))->id();

                        return (bool) $adminId && (int) $record->assigned_user_id !== (int) $adminId;
                    })
                    ->action(function (SupportConversation $record): void {
                        $admin = auth(config('filament.auth.guard', 'admin'))->user();
                        if (! $admin) {
                            return;
                        }

                        app(SupportChatService::class)->assignToAdmin($record, $admin);

                        Notification::make()
                            ->success()
                            ->title('Conversation assigned to you')
                            ->send();
                    }),
                Action::make('ai_summary')
                    ->label('AI summary')
                    ->icon('heroicon-o-sparkles')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Generate internal AI summary')
                    ->modalDescription('This creates an internal note visible to admins only.')
                    ->action(function (SupportConversation $record): void {
                        $admin = auth(config('filament.auth.guard', 'admin'))->user();
                        if (! $admin) {
                            return;
                        }

                        $note = app(SupportChatService::class)->addAiSummaryInternalNote($record, $admin);

                        Notification::make()
                            ->success()
                            ->title('AI summary added')
                            ->body(Str::limit($note->body, 140))
                            ->send();
                    }),
                EditAction::make(),
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (SupportConversation $record): bool => ! in_array($record->status, ['resolved', 'closed'], true))
                    ->action(fn (SupportConversation $record): bool => $record->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'handoff_requested' => false,
                    ])),
            ])
            ->defaultSort('last_message_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportConversations::route('/'),
            'edit' => Pages\EditSupportConversation::route('/{record}/edit'),
        ];
    }
}
