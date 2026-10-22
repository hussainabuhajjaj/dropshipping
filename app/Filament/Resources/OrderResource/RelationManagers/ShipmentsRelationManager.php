<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Domain\Orders\Models\Shipment;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Schemas\Components\Section;

class ShipmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'shipments';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tracking_number')
                    ->label('Tracking #')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('carrier')
                    ->label('Carrier')
                    ->badge(),

                TextColumn::make('logistic_name')
                    ->label('Shipping Method'),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn(Shipment $record) => match(true) {
                        $record->delivered_at !== null => 'Delivered',
                        $record->shipped_at !== null => 'Shipped',
                        $record->exception_code !== null => 'Exception',
                        default => 'Pending',
                    })
                    ->color(fn(Shipment $record) => match(true) {
                        $record->delivered_at !== null => 'success',
                        $record->shipped_at !== null => 'info',
                        $record->exception_code !== null => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('postage_amount')
                    ->label('Postage')
                    ->money(fn(Shipment $record) => $record->currency ?? 'USD'),

                TextColumn::make('shipped_at')
                    ->label('Shipped')
                    ->dateTime('M d, Y H:i'),

                TextColumn::make('delivered_at')
                    ->label('Delivered')
                    ->dateTime('M d, Y H:i'),

                BadgeColumn::make('exception_code')
                    ->label('Exception')
                    ->visible(fn(?Shipment $record) => $record?->exception_code !== null)
                    ->color('danger'),
            ])
            ->filters([
                //...existing code...
            ])
            ->headerActions([
                //...existing code...
            ])
            ->recordActions([
                Action::make('viewTracking')
                    ->label('View Tracking')
                    ->icon('heroicon-o-map-pin')
                    ->url(fn(Shipment $record) => $record->tracking_url)
                    ->openUrlInNewTab()
                    ->visible(fn(Shipment $record) => $record->tracking_url !== null),

                Action::make('addTracking')
                    ->label('Add/Edit Tracking')
                    ->icon('heroicon-o-pencil')
                    ->form([
                        Forms\Components\TextInput::make('tracking_number')
                            ->required(),
                        Forms\Components\TextInput::make('carrier'),
                        Forms\Components\TextInput::make('tracking_url')
                            ->url(),
                    ])
                    ->action(function (Shipment $record, array $data): void {
                        $record->update([
                            'tracking_number' => $data['tracking_number'],
                            'carrier' => $data['carrier'] ?? $record->carrier,
                            'tracking_url' => $data['tracking_url'] ?? $record->tracking_url,
                        ]);
                    }),

                Action::make('markDelivered')
                    ->label('Mark Delivered')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn(Shipment $record) => $record->delivered_at === null)
                    ->action(function (Shipment $record): void {
                        $record->update(['delivered_at' => now()]);
                    }),
            ])
            ->bulkActions([
                //...existing code...
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Shipment Details')
                ->schema([
                    TextEntry::make('tracking_number')
                        ->label('Tracking #')
                        ->copyable(),
                    TextEntry::make('carrier'),
                    TextEntry::make('logistic_name')
                        ->label('Shipping Method'),
                    TextEntry::make('postage_amount')
                        ->label('Postage'),
                ])
                ->columns(4),

            Section::make('Dates')
                ->schema([
                    TextEntry::make('shipped_at'),
                    TextEntry::make('delivered_at'),
                    TextEntry::make('exception_at')
                        ->visible(fn(Shipment $record) => $record->exception_at !== null),
                ])
                ->columns(3),
        ]);
    }
}
