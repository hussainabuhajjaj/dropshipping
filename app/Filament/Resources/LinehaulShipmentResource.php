<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Orders\Models\LinehaulShipment;
use App\Filament\Resources\LinehaulShipmentResource\Pages;
use App\Filament\Resources\OrderResource;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use BackedEnum;
use App\Filament\Resources\BaseResource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Throwable;

class LinehaulShipmentResource extends BaseResource
{
    protected static ?string $model = LinehaulShipment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';
    protected static string|\UnitEnum|null $navigationGroup = 'Fulfillment';
    protected static ?int $navigationSort = 12;
    protected static ?string $navigationLabel = 'Linehaul Shipments';

    public static function getLabel(): ?string
    {
        return 'Linehaul Shipment';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Linehaul Shipments';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.number')
                    ->label('Order #')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cj_order_id')
                    ->label('CJ Order ID')
                    ->toggleable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('cj_order_status')
                    ->label('CJ Status')
                    ->badge()
                    ->colors([
                        'success' => 'DELIVERED',
                        'warning' => 'SHIPPED',
                        'info' => 'UNSHIPPED',
                        'gray' => 'CREATED',
                        'danger' => 'CANCELLED',
                    ]),
                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Tracking')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cj_logistic_name')
                    ->label('Logistics')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_weight_kg')
                    ->label('Weight (kg)')
                    ->numeric(2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_fee')
                    ->label('Total fee')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cj_order_amount')
                    ->label('Order amount')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cj_postage_amount')
                    ->label('Postage')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(function (LinehaulShipment $record): string {
                        if ($record->arrived_at) {
                            return 'Arrived';
                        }
                        if ($record->dispatched_at) {
                            return 'Dispatched';
                        }
                        return 'Pending';
                    })
                    ->colors([
                        'success' => 'Arrived',
                        'warning' => 'Dispatched',
                        'gray' => 'Pending',
                    ]),
                Tables\Columns\TextColumn::make('dispatched_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('arrived_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('viewOrder')
                    ->label('View order')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (LinehaulShipment $record) => $record->order_id
                        ? OrderResource::getUrl('view', ['record' => $record->order_id])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (LinehaulShipment $record) => (bool) $record->order_id),
                Action::make('markDispatched')
                    ->label('Mark dispatched')
                    ->icon('heroicon-o-truck')
                    ->requiresConfirmation()
                    ->visible(fn (LinehaulShipment $record) => ! $record->dispatched_at)
                    ->action(function (LinehaulShipment $record): void {
                        $record->update([
                            'dispatched_at' => $record->dispatched_at ?? now(),
                        ]);
                    }),
                Action::make('markArrived')
                    ->label('Mark arrived')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->visible(fn (LinehaulShipment $record) => ! $record->arrived_at)
                    ->action(function (LinehaulShipment $record): void {
                        $record->update([
                            'dispatched_at' => $record->dispatched_at ?? now(),
                            'arrived_at' => $record->arrived_at ?? now(),
                        ]);
                    }),
                Action::make('syncCjOrder')
                    ->label('Sync CJ snapshot')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (LinehaulShipment $record) => (bool) $record->order?->cj_order_id)
                    ->action(function (LinehaulShipment $record): void {
                        try {
                            $client = app(CJDropshippingClient::class);
                            $cjOrderId = $record->order?->cj_order_id;
                            $response = $client->getOrderList([
                                'pageNum' => 1,
                                'pageSize' => 1,
                                'orderIds' => [$cjOrderId],
                            ]);

                            $data = is_array($response->data) ? $response->data : [];
                            $cjOrder = $data['list'][0] ?? null;
                            if (! is_array($cjOrder)) {
                                Notification::make()
                                    ->title('No CJ order found')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $record->applyCjOrder($cjOrder);
                            $record->save();

                            Notification::make()
                                ->title('CJ snapshot synced')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('CJ sync failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Linehaul shipment')
                ->schema([
                    TextEntry::make('order.number')->label('Order #'),
                    TextEntry::make('order.cj_order_id')->label('CJ Order ID'),
                    TextEntry::make('cj_order_num')->label('CJ Order #'),
                    TextEntry::make('cj_order_status')->label('CJ Status'),
                    TextEntry::make('tracking_number')->label('Tracking'),
                    TextEntry::make('cj_tracking_url')->label('Tracking URL'),
                    TextEntry::make('cj_logistic_name')->label('Logistics'),
                    TextEntry::make('total_weight_kg')->label('Weight (kg)'),
                    TextEntry::make('base_fee')->label('Base fee'),
                    TextEntry::make('per_kg_rate')->label('Per kg rate'),
                    TextEntry::make('total_fee')->label('Total fee'),
                    TextEntry::make('cj_order_amount')->label('Order amount'),
                    TextEntry::make('cj_product_amount')->label('Product amount'),
                    TextEntry::make('cj_postage_amount')->label('Postage amount'),
                    TextEntry::make('dispatched_at')->dateTime(),
                    TextEntry::make('arrived_at')->dateTime(),
                    TextEntry::make('cj_created_at')->label('CJ created')->dateTime(),
                    TextEntry::make('cj_paid_at')->label('CJ paid')->dateTime(),
                    TextEntry::make('cj_store_created_at')->label('Store created')->dateTime(),
                    TextEntry::make('cj_storage_id')->label('Storage ID'),
                    TextEntry::make('cj_storage_name')->label('Storage name'),
                    TextEntry::make('cj_shipping_country_code')->label('Ship country'),
                    TextEntry::make('cj_shipping_province')->label('Ship province'),
                    TextEntry::make('cj_shipping_city')->label('Ship city'),
                    TextEntry::make('cj_shipping_phone')->label('Ship phone'),
                    TextEntry::make('cj_shipping_customer_name')->label('Ship customer'),
                    TextEntry::make('cj_shipping_address')->label('Ship address'),
                    TextEntry::make('cj_remark')->label('CJ remark'),
                ])->columns(3),
            Section::make('Snapshot')
                ->schema([
                    TextEntry::make('shipment_snapshot')
                        ->label('Snapshot')
                        ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '—')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLinehaulShipments::route('/'),
            'view' => Pages\ViewLinehaulShipment::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
