<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Products\Models\SupplierProduct;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Products\Models\ProductVariant;
use App\Filament\Resources\SupplierProductResource\Pages;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierProductResource extends BaseResource
{
    protected static ?string $model = SupplierProduct::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';
    protected static string|\UnitEnum|null $navigationGroup = 'Suppliers';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Supplier Mapping')
                ->schema([
                    Forms\Components\Select::make('product_variant_id')
                        ->label('Variant')
                        ->options(fn () => ProductVariant::query()->pluck('title', 'id'))
                        ->searchable()
                        ->required(),
                    Forms\Components\Select::make('fulfillment_provider_id')
                        ->label('Supplier / Provider')
                        ->options(fn () => FulfillmentProvider::query()->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('external_product_id')->required(),
                    Forms\Components\TextInput::make('external_sku'),
                    Forms\Components\TextInput::make('cost_price')->numeric(),
                    Forms\Components\TextInput::make('currency')->default('USD'),
                    Forms\Components\TextInput::make('lead_time_days')->numeric()->default(0),
                    Forms\Components\Toggle::make('is_active')->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('productVariant.title')->label('Variant')->searchable(),
                Tables\Columns\TextColumn::make('fulfillmentProvider.name')->label('Provider')->searchable(),
                Tables\Columns\TextColumn::make('external_product_id')->label('External ID')->searchable(),
                Tables\Columns\TextColumn::make('external_sku')->label('SKU'),
                Tables\Columns\TextColumn::make('cost_price')->money(fn ($record) => $record->currency)->sortable(),
                Tables\Columns\TextColumn::make('lead_time_days')->label('Lead time')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplierProducts::route('/'),
            'create' => Pages\CreateSupplierProduct::route('/create'),
            'edit' => Pages\EditSupplierProduct::route('/{record}/edit'),
        ];
    }
}



