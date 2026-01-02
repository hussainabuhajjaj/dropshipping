<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingRateResource\Pages;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use App\Filament\Resources\BaseResource;

class ShippingRateResource extends BaseResource
{
    protected static ?string $model = ShippingRate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 26;
    protected static bool $shouldRegisterNavigation = true;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Rate')
                ->schema([
                    Forms\Components\Select::make('shipping_zone_id')
                        ->label('Zone')
                        ->options(fn () => ShippingZone::query()->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('name')->required()->maxLength(120),
                    Forms\Components\TextInput::make('rate')->numeric()->minValue(0)->required(),
                    Forms\Components\TextInput::make('carrier')->maxLength(120),
                    Forms\Components\Toggle::make('is_active')->default(true),
                ])->columns(2),
            Section::make('Conditions')
                ->schema([
                    Forms\Components\TextInput::make('min_weight')->numeric()->minValue(0)->suffix('kg'),
                    Forms\Components\TextInput::make('max_weight')->numeric()->minValue(0)->suffix('kg'),
                    Forms\Components\TextInput::make('min_price')->numeric()->minValue(0)->prefix('$'),
                    Forms\Components\TextInput::make('max_price')->numeric()->minValue(0)->prefix('$'),
                    Forms\Components\TextInput::make('delivery_min_days')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('delivery_max_days')->numeric()->minValue(0),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('zone.name')->label('Zone')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('rate')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('carrier')->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('shipping_zone_id')->label('Zone')->options(fn () => ShippingZone::query()->pluck('name', 'id')),
                Tables\Filters\TernaryFilter::make('is_active'),
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
            'index' => Pages\ListShippingRates::route('/'),
            'create' => Pages\CreateShippingRate::route('/create'),
            'edit' => Pages\EditShippingRate::route('/{record}/edit'),
        ];
    }
}
