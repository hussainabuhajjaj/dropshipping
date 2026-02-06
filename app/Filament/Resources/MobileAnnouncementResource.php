<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MobileAnnouncementResource\Pages;
use App\Jobs\SendMobileAnnouncementNotificationsJob;
use App\Models\MobileAnnouncement;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class MobileAnnouncementResource extends BaseResource
{
    protected static ?string $model = MobileAnnouncement::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';

    protected static UnitEnum|string|null $navigationGroup = 'Storefront';

    protected static ?int $navigationSort = 26;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Mobile announcement')
                ->schema([
                    Forms\Components\Select::make('locale')
                        ->options([
                            'en' => 'English',
                            'fr' => 'French',
                        ])
                        ->native(false)
                        ->nullable()
                        ->placeholder('All languages')
                        ->helperText('Leave empty to show this announcement for all languages.'),
                    Forms\Components\Toggle::make('enabled')
                        ->label('Enabled')
                        ->default(true),
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(120),
                    Forms\Components\Textarea::make('body')
                        ->required()
                        ->rows(4),
                    Forms\Components\FileUpload::make('image')
                        ->label('Image')
                        ->disk('public')
                        ->directory('announcements')
                        ->image()
                        ->helperText('Optional image for the announcement card.'),
                    Forms\Components\TextInput::make('action_href')
                        ->label('Action link')
                        ->maxLength(500)
                        ->helperText('Optional link (e.g., /(tabs)/home or https://example.com).'),
                    Section::make('Notification channels')
                        ->schema([
                            Forms\Components\Toggle::make('send_database')
                                ->label('In-app notification (database)')
                                ->default(true),
                            Forms\Components\Toggle::make('send_push')
                                ->label('Push notification (Expo)')
                                ->default(true),
                            Forms\Components\Toggle::make('send_email')
                                ->label('Email notification')
                                ->default(false),
                        ])
                        ->columns(3),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('locale')
                    ->label('Locale')
                    ->formatStateUsing(fn (?string $state) => $state ?: 'All')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('enabled')
                    ->label('Enabled')
                    ->sortable(),
                Tables\Columns\TextColumn::make('notified_at')
                    ->label('Notified')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('send_notifications')
                    ->label(fn (MobileAnnouncement $record): string => $record->notified_at ? 'Resend' : 'Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn (MobileAnnouncement $record): bool => (bool) $record->enabled)
                    ->requiresConfirmation()
                    ->modalHeading(fn (MobileAnnouncement $record): string => ($record->notified_at ? 'Resend' : 'Send') . ' announcement notification')
                    ->modalDescription('This will notify customers via the enabled channels (database, push, email).')
                    ->action(function (MobileAnnouncement $record): void {
                        dispatch(new SendMobileAnnouncementNotificationsJob($record->id));

                        Notification::make()
                            ->success()
                            ->title('Announcement queued')
                            ->body('Notifications will be sent shortly.')
                            ->send();
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMobileAnnouncements::route('/'),
            'create' => Pages\CreateMobileAnnouncement::route('/create'),
            'edit' => Pages\EditMobileAnnouncement::route('/{record}/edit'),
        ];
    }
}
