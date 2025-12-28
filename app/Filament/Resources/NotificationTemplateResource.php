<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationTemplateResource\Pages;
use App\Models\NotificationTemplate;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\BaseResource;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;

class NotificationTemplateResource extends BaseResource
{
    protected static ?string $model = NotificationTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell';
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 27;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Template')
                ->schema([
                    Forms\Components\TextInput::make('key')
                        ->label('Template Key')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Unique identifier (e.g., order_confirmed, order_shipped)')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(120),
                    Forms\Components\Textarea::make('description')
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('channel')
                        ->options([
                            'email' => 'Email',
                            'sms' => 'SMS',
                            'both' => 'Email + SMS',
                        ])
                        ->default('email')
                        ->required(),
                    Forms\Components\Toggle::make('is_enabled')
                        ->label('Enabled')
                        ->default(true),
                ])->columns(2),
            Section::make('Email Content')
                ->schema([
                    Forms\Components\TextInput::make('subject')
                        ->label('Email Subject')
                        ->maxLength(200),
                    Forms\Components\Textarea::make('body')
                        ->label('Message Body')
                        ->required()
                        ->rows(8)
                        ->helperText('Use {{ variable_name }} for dynamic content')
                        ->columnSpanFull(),
                ]),
            Section::make('Sender')
                ->schema([
                    Forms\Components\TextInput::make('sender_name')
                        ->label('From Name')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('sender_email')
                        ->label('From Email')
                        ->email()
                        ->maxLength(120),
                ])->columns(2),
            Section::make('Available Variables')
                ->schema([
                    Forms\Components\Placeholder::make('variables_help')
                        ->label('')
                        ->content(fn ($record) => $record ? implode(', ', array_map(fn($v) => "{{ $v }}", $record->getAvailableVariables())) : 'Save template to see available variables'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('key')->badge()->searchable(),
                Tables\Columns\TextColumn::make('channel')->badge(),
                Tables\Columns\IconColumn::make('is_enabled')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')->options([
                    'email' => 'Email',
                    'sms' => 'SMS',
                    'both' => 'Both',
                ]),
                Tables\Filters\TernaryFilter::make('is_enabled'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
               CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationTemplates::route('/'),
            'create' => Pages\CreateNotificationTemplate::route('/create'),
            'edit' => Pages\EditNotificationTemplate::route('/{record}/edit'),
        ];
    }
}
