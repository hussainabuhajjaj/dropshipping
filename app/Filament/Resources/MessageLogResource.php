<?php

namespace App\Filament\Resources;

use App\Domain\Messaging\Models\MessageLog;
use App\Enums\MessageChannel;
use App\Filament\Resources\MessageLogResource\Pages;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use UnitEnum;

class MessageLogResource extends Resource
{
    protected static ?string $model = MessageLog::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left';

    protected static UnitEnum|string|null $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Message Information')
                    ->schema([
                        Forms\Components\Select::make('message_template_id')
                            ->relationship('messageTemplate', 'title')
                            ->disabled(),

                        Forms\Components\Select::make('order_id')
                            ->relationship('order', 'number')
                            ->disabled(),

                        Forms\Components\Select::make('shipment_id')
                            ->relationship('shipment', 'tracking_number')
                            ->disabled(),
                    ])
                    ->columns(3),

                Section::make('Recipient & Channel')
                    ->schema([
                        Forms\Components\TextInput::make('recipient')
                            ->disabled(),

                        Forms\Components\TextInput::make('channel')
                            ->disabled(),

                        Forms\Components\Toggle::make('is_automatic')
                            ->disabled(),
                    ])
                    ->columns(3),

                Section::make('Message Content')
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('message_content')
                            ->disabled()
                            ->rows(6)
                            ->columnSpanFull(),
                    ]),

                Section::make('Status & Tracking')
                    ->schema([
                        Forms\Components\TextInput::make('status')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => ucfirst($state)),

                        Forms\Components\DateTimePicker::make('sent_at')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('opened_at')
                            ->label('Opened At')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('clicked_at')
                            ->label('Clicked At')
                            ->disabled(),
                    ])
                    ->columns(2),

                Section::make('Details')
                    ->schema([
                        Forms\Components\TextInput::make('external_message_id')
                            ->disabled(),

                        Forms\Components\Textarea::make('error_message')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('messageTemplate.title')
                    ->label('Template')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('order.number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('shipment.tracking_number')
                    ->label('Tracking')
                    ->searchable(),

                Tables\Columns\TextColumn::make('recipient')
                    ->searchable(),

                Tables\Columns\TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn ($state) => MessageChannel::from($state)->label()),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($record) => $record->getStatusBadgeColor()),

                Tables\Columns\IconColumn::make('is_automatic')
                    ->label('Auto')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                Tables\Columns\IconColumn::make('opened_at')
                    ->label('Opened')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->color(fn ($state) => $state ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->options(MessageChannel::labels()),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                        'opened' => 'Opened',
                        'clicked' => 'Clicked',
                    ]),

                Tables\Filters\TernaryFilter::make('is_automatic')
                    ->label('Automatic'),

                Tables\Filters\Filter::make('opened')
                    ->label('Opened')
                    ->query(fn ($query) => $query->whereNotNull('opened_at')),


            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('sent_at', 'desc')
            ->defaultPaginationPageOption(50);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessageLogs::route('/'),
            'view' => Pages\ViewMessageLog::route('/{record}'),
        ];
    }
}
