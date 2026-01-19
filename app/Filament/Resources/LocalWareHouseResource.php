<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\LocalWareHouseResource\Pages;
use App\Models\LocalWareHouse;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LocalWareHouseResource extends BaseResource
{
    protected static ?string $model = LocalWareHouse::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';
    protected static string|\UnitEnum|null $navigationGroup = 'Fulfillment';
    protected static ?int $navigationSort = 35;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Warehouse')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->maxLength(30),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Default warehouse'),
                ])->columns(3),
            Section::make('Address')
                ->schema([
                    Forms\Components\TextInput::make('line1')
                        ->label('Address line 1')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('line2')
                        ->label('Address line 2')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('city')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('state')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('postal_code')
                        ->label('Postal code')
                        ->maxLength(30),
                    Forms\Components\TextInput::make('country')
                        ->maxLength(2)
                        ->default('CN')
                        ->helperText('2-letter country code'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('line1')
                    ->label('Address')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('state')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('country')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->boolean()
                    ->label('Default'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocalWareHouses::route('/'),
            'create' => Pages\CreateLocalWareHouse::route('/create'),
            'edit' => Pages\EditLocalWareHouse::route('/{record}/edit'),
        ];
    }
}
