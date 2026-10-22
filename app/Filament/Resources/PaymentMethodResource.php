<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentMethodResource\Pages;
use App\Models\PaymentMethod;
use BackedEnum;
use App\Filament\Resources\BaseResource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentMethodResource extends BaseResource
{
    protected static ?string $model = PaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static string|\UnitEnum|null $navigationGroup = 'Payments';
    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.email')->label('Customer')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('provider')->badge()->sortable(),
                Tables\Columns\TextColumn::make('brand')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('last4')->label('Last 4')->toggleable(),
                Tables\Columns\TextColumn::make('exp_month')->label('Exp')->toggleable(),
                Tables\Columns\IconColumn::make('is_default')->boolean()->label('Default'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_default'),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentMethods::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}



