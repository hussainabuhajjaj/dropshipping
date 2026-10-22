<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Domain\Orders\Models\TrackingEvent;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TrackingEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'orderItems.shipments.trackingEvents';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status_code')
                    ->label('Status')
                    ->badge()
                    ->color(fn(TrackingEvent $record) => match($record->status_code) {
                        'delivered' => 'success',
                        'in_transit' => 'info',
                        'out_for_delivery' => 'warning',
                        'returned' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('status_label'),

                TextColumn::make('description')
                    ->limit(50),

                TextColumn::make('location')
                    ->limit(40),

                TextColumn::make('occurred_at')
                    ->label('Time')
                    ->dateTime('M d, Y H:i'),
            ])
            ->filters([
                //...existing code...
            ])
            ->headerActions([
                //...existing code...
            ])
            ->actions([
                //...existing code...
            ])
            ->bulkActions([
                //...existing code...
            ])
            ->defaultSort('occurred_at', 'desc');
    }
}
