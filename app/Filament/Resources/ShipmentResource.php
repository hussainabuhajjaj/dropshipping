<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Orders\Models\Shipment;
use App\Filament\Resources\ShipmentResource\Pages;
use App\Filament\Resources\ShipmentResource\RelationManagers\TrackingEventsRelationManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use App\Filament\Resources\BaseResource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShipmentResource extends BaseResource
{
    protected static ?string $model = Shipment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';
    protected static string|\UnitEnum|null $navigationGroup = 'Fulfillment';
    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->getStateUsing(fn (Shipment $record): ?string => $record->order?->number ?? $record->orderItem?->order?->number)
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('order', fn ($orderQuery) => $orderQuery->where('number', 'like', "%{$search}%"))
                            ->orWhereHas('orderItem.order', fn ($orderQuery) => $orderQuery->where('number', 'like', "%{$search}%"));
                    }),
                Tables\Columns\TextColumn::make('order_item_id')->label('Item ID')->sortable(),
                Tables\Columns\TextColumn::make('tracking_number')->label('Tracking')->searchable()->placeholder('Pending'),
                Tables\Columns\TextColumn::make('shipment_order_id')->label('Shipment Order')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cj_order_id')->label('CJ Order')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('carrier')->sortable(),
                Tables\Columns\TextColumn::make('shipped_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('delivered_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('markDelivered')
                    ->label('Mark delivered')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->action(function (Shipment $record): void {
                        $record->update(['delivered_at' => $record->delivered_at ?? now()]);
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Shipment')
                ->schema([
                    TextEntry::make('order.number')->label('Order #'),
                    TextEntry::make('tracking_number')->label('Tracking')->placeholder('Pending'),
                    TextEntry::make('shipment_order_id')->label('Shipment Order'),
                    TextEntry::make('cj_order_id')->label('CJ Order'),
                    TextEntry::make('carrier'),
                    TextEntry::make('shipped_at')->dateTime(),
                    TextEntry::make('delivered_at')->dateTime(),
                ])->columns(3),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            TrackingEventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShipments::route('/'),
            'view' => Pages\ViewShipment::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}



