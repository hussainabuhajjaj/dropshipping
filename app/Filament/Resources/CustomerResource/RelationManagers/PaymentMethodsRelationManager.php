<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentMethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentMethods';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last4')
                    ->label('Last 4')
                    ->formatStateUsing(fn ($state) => $state ? '**** ' . $state : '—'),
                Tables\Columns\TextColumn::make('exp_month')
                    ->label('Exp month')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('exp_year')
                    ->label('Exp year')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('nickname')
                    ->toggleable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
