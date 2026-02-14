<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Filament\Resources\OrderResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'orderItems';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.number')
                    ->label('Order')
                    ->searchable()
                    ->url(fn ($record) => OrderResource::getUrl('view', ['record' => $record->order_id])),
                Tables\Columns\TextColumn::make('order.status')
                    ->label('Order Status')
                    ->badge(),
                Tables\Columns\TextColumn::make('productVariant.sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->money(fn ($record) => $record->order?->currency ?? 'USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->money(fn ($record) => $record->order?->currency ?? 'USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fulfillment_status')
                    ->badge()
                    ->label('Fulfillment')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Purchased At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}

