<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\MetaReplyStatus;
use App\Filament\Resources\MetaReplyResource\Pages;
use App\Jobs\SendMetaReplyJob;
use App\Models\MetaInboxMessage;
use App\Models\MetaReply;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use UnitEnum;

class MetaReplyResource extends BaseResource
{
    protected static ?string $model = MetaReply::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static UnitEnum|string|null $navigationGroup = 'Integrations';

    protected static bool $adminOnly = true;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('message.platform')->label('Platform')->disabled(),
                Forms\Components\TextInput::make('message.channel')->label('Channel')->disabled(),
                Forms\Components\Textarea::make('message.sender_handle')->label('From')->disabled(),
                Forms\Components\Textarea::make('message.text')->label('Incoming message')->disabled(),
                Forms\Components\TextInput::make('classification')->label('Classification')->disabled(),
                Forms\Components\Textarea::make('draft_text')
                    ->label('Reply draft')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('message.sender_handle')->label('From')->searchable(),
                Tables\Columns\TextColumn::make('message.platform')->badge(),
                Tables\Columns\TextColumn::make('message.channel')->badge(),
                Tables\Columns\TextColumn::make('classification')->badge(),
                Tables\Columns\TextColumn::make('draft_text')->label('Draft')->limit(60),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'sent' => 'success',
                    'auto' => 'info',
                    'approved' => 'warning',
                    'failed' => 'danger',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('created_at')->label('Received')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(MetaReplyStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->value])->all()),
                Tables\Filters\SelectFilter::make('channel')
                    ->options(['comment' => 'Comment', 'message' => 'Message']),
            ])
            ->recordActions([
                Tables\Actions\Action::make('approve_send')
                    ->label('Approve & Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (MetaReply $record): bool => in_array($record->status->value, ['draft', 'auto', 'approved'], true))
                    ->action(function (MetaReply $record): void {
                        $record->approve(Auth::id());
                        SendMetaReplyJob::dispatch($record->id)->onConnection('redis');
                    })
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (MetaReply $record): bool => $record->status->value !== 'sent')
                    ->action(fn (MetaReply $record) => $record->update(['status' => MetaReplyStatus::Rejected]))
                    ->requiresConfirmation(),
            ])
            ->bulkActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMetaReplies::route('/'),
        ];
    }
}
