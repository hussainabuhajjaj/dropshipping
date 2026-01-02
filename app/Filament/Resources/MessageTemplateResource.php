<?php

namespace App\Filament\Resources;

use App\Domain\Messaging\Models\MessageTemplate;
use App\Domain\Messaging\Services\MessageTemplateService;
use App\Enums\MessageChannel;
use App\Enums\MessageTemplateType;
use BackedEnum;
use App\Enums\MessageTriggerType;
use App\Filament\Resources\MessageTemplateResource\Pages;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use UnitEnum;

class MessageTemplateResource extends Resource
{
    protected static ?string $model = MessageTemplate::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope';

    protected static UnitEnum|string|null $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Template Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(MessageTemplate::class, 'name', ignoreRecord: true)
                            ->helperText('Identifier for system use'),

                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->helperText('Display name for admin'),

                        Forms\Components\Select::make('type')
                            ->options(MessageTemplateType::labels())
                            ->required()
                            ->native(false),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->helperText('When and why to use this template'),
                    ])
                    ->columns(2),

                Section::make('Message Content')
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->helperText('Email subject - use {placeholders} for substitution')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('message')
                            ->required()
                            ->rows(6)
                            ->helperText('Use {placeholder} syntax for dynamic content')
                            ->columnSpanFull(),

                        Forms\Components\Repeater::make('available_placeholders')
                            ->label('Available Placeholders')
                            ->simple(
                                Forms\Components\TextInput::make('placeholder')
                                    ->placeholder('e.g., order_number, customer_name')
                            )
                            ->helperText('List all placeholders available for this template')
                            ->columnSpanFull(),
                    ]),

                Section::make('Placeholder Configuration')
                    ->schema([
                        Forms\Components\Repeater::make('required_placeholders')
                            ->label('Required Placeholders')
                            ->simple(
                                Forms\Components\TextInput::make('placeholder')
                                    ->placeholder('e.g., order_number')
                            )
                            ->helperText('Which placeholders must be provided to send')
                            ->columnSpanFull(),
                    ]),

                Section::make('Channel Configuration')
                    ->schema([
                        Forms\Components\Select::make('default_channel')
                            ->options(MessageChannel::labels())
                            ->default('email')
                            ->required()
                            ->native(false),

                        Forms\Components\CheckboxList::make('enabled_channels')
                            ->options(MessageChannel::labels())
                            ->default(['email'])
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Auto-Send Configuration')
                    ->schema([
                        Forms\Components\Toggle::make('send_automatically')
                            ->label('Enable Auto-Send')
                            ->reactive(),

                        Forms\Components\MultiSelect::make('trigger_types')
                            ->label('Send When')
                            ->options(MessageTriggerType::labels())
                            ->visible(fn ($get) => $get('send_automatically'))
                            ->multiple(),

                        Forms\Components\TextInput::make('auto_send_delay_hours')
                            ->label('Delay Before Sending (hours)')
                            ->numeric()
                            ->visible(fn ($get) => $get('send_automatically'))
                            ->helperText('Leave empty to send immediately'),
                    ])
                    ->columns(1),

                Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => MessageTemplateType::from($state)->label()),

                Tables\Columns\TextColumn::make('default_channel')
                    ->badge()
                    ->formatStateUsing(fn ($state) => MessageChannel::from($state)->label()),

                Tables\Columns\IconColumn::make('send_automatically')
                    ->label('Auto-Send')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('messageLogs_count')
                    ->label('Messages Sent')
                    ->counts('messageLogs')
                    ->alignment('center'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(MessageTemplateType::labels()),

                Tables\Filters\SelectFilter::make('default_channel')
                    ->options(MessageChannel::labels()),

                Tables\Filters\TernaryFilter::make('send_automatically')
                    ->label('Auto-Send'),

                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('preview')
                    ->icon('heroicon-o-eye')
                    ->modal()
                    ->modalContent(fn ($record) => view('filament.modals.template-preview', [
                        'content' => app(MessageTemplateService::class)->testTemplate($record),
                    ])),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessageTemplates::route('/'),
            'create' => Pages\CreateMessageTemplate::route('/create'),
            'edit' => Pages\EditMessageTemplate::route('/{record}/edit'),
            'view' => Pages\ViewMessageTemplate::route('/{record}'),
        ];
    }
}
