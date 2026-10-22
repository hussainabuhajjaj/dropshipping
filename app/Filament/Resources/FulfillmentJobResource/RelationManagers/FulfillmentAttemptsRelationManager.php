<?php

declare(strict_types=1);

namespace App\Filament\Resources\FulfillmentJobResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FulfillmentAttemptsRelationManager extends RelationManager
{
    protected static string $relationship = 'attempts';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('attempt_no')->label('Attempt')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('error_message')->limit(60),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
