<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShipmentResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TrackingEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'trackingEvents';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status_code')->label('Status')->badge(),
                Tables\Columns\TextColumn::make('status_label')->limit(30),
                Tables\Columns\TextColumn::make('description')->limit(60),
                Tables\Columns\TextColumn::make('location')->limit(30),
                Tables\Columns\TextColumn::make('occurred_at')->dateTime(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
