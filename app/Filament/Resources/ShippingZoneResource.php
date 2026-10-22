<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingZoneResource\Pages;
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

class ShippingZoneResource extends BaseResource
{
    protected static ?string $model = ShippingZone::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 25;
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Zone')
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Zone name')->required()->maxLength(120),
                    Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
                    Forms\Components\TextInput::make('sort')->numeric()->default(0),
                ])->columns(3),
            Section::make('Countries')
                ->schema([
                    Forms\Components\TagsInput::make('country_codes')
                        ->label('Country codes (ISO alpha-2)')
                        ->helperText('Example: US, CA, GB, AU')
                        ->placeholder('Add country code')
                        ->suggestions(['US','CA','GB','AU','DE','FR','IT','ES','NL','SE','NO','FI','DK','PL','CN','JP'])
                        ->reorderable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('sort')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('rates_count')
                    ->label('Rates')
                    ->counts('rates')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
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
            'index' => Pages\ListShippingZones::route('/'),
            'create' => Pages\CreateShippingZone::route('/create'),
            'edit' => Pages\EditShippingZone::route('/{record}/edit'),
        ];
    }
}
