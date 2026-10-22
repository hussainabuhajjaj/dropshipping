<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentWebhooksRelationManager extends RelationManager
{
    protected static string $relationship = 'webhooks';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider')->badge(),
                Tables\Columns\TextColumn::make('external_event_id')->label('Event ID')->limit(30),
                Tables\Columns\TextColumn::make('processed_at')->dateTime(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
