<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('line1')
                    ->label('Address line 1')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('line2')
                    ->label('Address line 2')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable(),
                Tables\Columns\TextColumn::make('state')
                    ->label('Region')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('country')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('postal_code')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
            ])
            ->defaultSort('is_default', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
