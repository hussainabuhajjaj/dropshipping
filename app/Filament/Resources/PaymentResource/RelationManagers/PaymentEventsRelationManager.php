<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->label('Event'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('message')->limit(60),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
