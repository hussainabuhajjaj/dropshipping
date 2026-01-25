<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AdminNotificationResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use UnitEnum;
use Illuminate\Support\HtmlString;

class AdminNotificationResource extends BaseResource
{
    protected static ?string $model = DatabaseNotification::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-bell-alert';
    protected static UnitEnum|string|null $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 97;
    protected static bool $staffReadOnly = true;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('notifiable_type', User::class)
            ->latest();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(self::getColumns())
            ->filters(self::getFilters())
            ->recordActions(self::getRecordActions())
            ->toolbarActions(self::getToolbarActions())
            ->defaultSort('created_at', 'desc');
    }

    protected static function getColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('data.title')
                ->label('Title')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query
                        ->where('data->title', 'like', "%{$search}%")
                        ->orWhere('data->body', 'like', "%{$search}%");
                })
                ->limit(40),
            Tables\Columns\TextColumn::make('data.body')
                ->label('Message')
                ->limit(60)
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('notifiable.name')
                ->label('Recipient')
                ->sortable()
                ->toggleable(),
            Tables\Columns\TextColumn::make('read_at')
                ->label('Status')
                ->formatStateUsing(fn (?string $state): string => $state ? 'Read' : 'Unread')
                ->badge()
                ->color(fn (?string $state): string => $state ? 'gray' : 'warning'),
            Tables\Columns\TextColumn::make('created_at')
                ->label('Sent')
                ->since()
                ->sortable(),
        ];
    }

    protected static function getFilters(): array
    {
        return [
            Tables\Filters\Filter::make('unread')
                ->label('Unread')
                ->query(fn (Builder $query): Builder => $query->whereNull('read_at')),
            Tables\Filters\Filter::make('read')
                ->label('Read')
                ->query(fn (Builder $query): Builder => $query->whereNotNull('read_at')),
        ];
    }

    protected static function getRecordActions(): array
    {
        return [
            Action::make('details')
                ->label('Details')
                ->icon('heroicon-o-eye')
                ->modalHeading(fn (DatabaseNotification $record): string => (string) ($record->data['title'] ?? 'Notification'))
                ->modalContent(fn (DatabaseNotification $record): HtmlString => new HtmlString(
                    view('filament.notifications.details', ['record' => $record])->render()
                )),
            Action::make('mark_read')
                ->label('Mark read')
                ->icon('heroicon-o-check')
                ->visible(fn (DatabaseNotification $record): bool => $record->read_at === null)
                ->action(fn (DatabaseNotification $record) => $record->markAsRead()),
            Action::make('mark_unread')
                ->label('Mark unread')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn (DatabaseNotification $record): bool => $record->read_at !== null)
                ->action(fn (DatabaseNotification $record) => $record->markAsUnread()),
        ];
    }

    protected static function getToolbarActions(): array
    {
        return [
            BulkAction::make('mark_read')
                ->label('Mark selected read')
                ->action(fn ($records) => $records->each->markAsRead()),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminNotifications::route('/'),
        ];
    }
}
