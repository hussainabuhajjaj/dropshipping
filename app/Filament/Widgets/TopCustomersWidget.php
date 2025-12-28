<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopCustomersWidget extends BaseWidget
{
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Customer::query()
                    ->whereNotNull('metadata->lifetime_value')
                    ->orderByRaw('CAST(JSON_EXTRACT(metadata, "$.lifetime_value") AS DECIMAL(12,2)) DESC')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('lifetime_value')
                    ->label('Lifetime Value')
                    ->getStateUsing(fn (Customer $record) => $record->metadata['lifetime_value'] ?? 0)
                    ->money('USD')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Member Since')
                    ->date()
                    ->sortable(),
            ])
            ->heading('Top 10 Customers by Lifetime Value')
            ->defaultSort('lifetime_value', 'desc');
    }
}
