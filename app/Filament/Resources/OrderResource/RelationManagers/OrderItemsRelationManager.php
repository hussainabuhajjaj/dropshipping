<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Orders\Services\TrackingService;
use App\Domain\Orders\Models\OrderAuditLog;
use App\Jobs\DispatchFulfillmentJob;
use App\Events\Orders\OrderDelivered;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'orderItems';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('productVariant.title')->label('Variant'),
                Tables\Columns\TextColumn::make('quantity'),
                Tables\Columns\TextColumn::make('unit_price')->money(fn ($record) => $record->order->currency),
                Tables\Columns\TextColumn::make('fulfillment_status')->badge(),
                Tables\Columns\SelectColumn::make('fulfillment_provider_id')
                    ->label('Fulfillment Provider')
                    ->options(fn () => FulfillmentProvider::query()->where('is_active', true)->pluck('name', 'id'))
                    ->selectablePlaceholder(false),
                Tables\Columns\TextColumn::make('supplierProduct.external_product_id')
                    ->label('Supplier Link')
                    ->url(fn ($record) => $record->supplierProduct?->external_product_id
                        ? 'https://www.aliexpress.com/item/'.$record->supplierProduct->external_product_id.'.html'
                        : null, true),
                Tables\Columns\TextColumn::make('fulfillmentJob.status')
                    ->label('Job')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'succeeded', 'fulfilled' => 'success',
                        'failed' => 'danger',
                        'needs_action' => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn ($record) => $record->fulfillmentJob?->last_error),
                Tables\Columns\TextColumn::make('tracking_number_display')
                    ->label('Tracking')
                    ->getStateUsing(fn ($record) => $record->shipments()->latest('shipped_at')->value('tracking_number'))
                    ->tooltip(fn ($record) => $record->shipments()->latest('shipped_at')->value('tracking_url'))
                    ->copyable(),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([
                Action::make('approveCjFulfillment')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->fulfillmentProvider && $record->fulfillmentProvider->code === 'cj' && $record->fulfillment_status === 'pending')
                    ->action(function ($record): void {
                        \App\Jobs\DispatchFulfillmentJob::dispatch($record->id);
                        $record->update(['fulfillment_status' => 'fulfilling']);
                        \App\Domain\Orders\Models\OrderAuditLog::create([
                            'order_id' => $record->order_id,
                            'user_id' => auth()->id(),
                            'action' => 'cj_fulfillment_approved',
                            'note' => 'CJ fulfillment approved by admin',
                            'payload' => ['order_item_id' => $record->id],
                        ]);
                    }),
               Action::make('dispatchFulfillment')
                    ->label('Dispatch')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->fulfillmentProvider !== null)
                    ->action(function ($record): void {
                        DispatchFulfillmentJob::dispatch($record->id);

                        $record->update(['fulfillment_status' => 'fulfilling']);

                        OrderAuditLog::create([
                            'order_id' => $record->order_id,
                            'user_id' => auth()->id(),
                            'action' => 'fulfillment_dispatched',
                            'note' => 'Dispatched to provider',
                            'payload' => ['order_item_id' => $record->id],
                        ]);
                    }),
                Action::make('addTracking')
                    ->label('Add Tracking')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Forms\Components\TextInput::make('tracking_number')->required(),
                        Forms\Components\TextInput::make('carrier'),
                        Forms\Components\TextInput::make('tracking_url')->url(),
                        Forms\Components\DateTimePicker::make('shipped_at')->default(now()),
                    ])
                    ->action(function ($record, array $data): void {
                        $tracking = app(TrackingService::class);
                        $tracking->recordShipment($record, [
                            'tracking_number' => $data['tracking_number'],
                            'carrier' => $data['carrier'] ?? null,
                            'tracking_url' => $data['tracking_url'] ?? null,
                            'shipped_at' => $data['shipped_at'] ?? now(),
                        ]);

                        if ($record->fulfillment_status !== 'fulfilled') {
                            $record->update(['fulfillment_status' => 'fulfilled']);
                        }
                        OrderAuditLog::create([
                            'order_id' => $record->order_id,
                            'user_id' => auth()->id(),
                            'action' => 'tracking_added',
                            'note' => 'Tracking number added',
                            'payload' => $data,
                        ]);
                    }),
               Action::make('addTrackingEvent')
                    ->label('Add Tracking Event')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Forms\Components\TextInput::make('tracking_number')
                            ->label('Tracking number')
                            ->required(),
                        Forms\Components\TextInput::make('status_code')->required(),
                        Forms\Components\TextInput::make('status_label'),
                        Forms\Components\TextInput::make('location'),
                        Forms\Components\DateTimePicker::make('occurred_at')->default(now())->required(),
                        Forms\Components\Textarea::make('description')->rows(2),
                    ])
                    ->action(function ($record, array $data): void {
                        $tracking = app(TrackingService::class);
                        $shipment = $record->shipments()
                            ->where('tracking_number', $data['tracking_number'])
                            ->first();

                        if (! $shipment) {
                            $shipment = $tracking->recordShipment($record, [
                                'tracking_number' => $data['tracking_number'],
                                'carrier' => null,
                                'shipped_at' => now(),
                            ]);
                        }

                        $tracking->recordEvent($shipment, [
                            'status_code' => $data['status_code'],
                            'status_label' => $data['status_label'] ?? null,
                            'description' => $data['description'] ?? null,
                            'location' => $data['location'] ?? null,
                            'occurred_at' => $data['occurred_at'],
                        ]);
                        OrderAuditLog::create([
                            'order_id' => $record->order_id,
                            'user_id' => auth()->id(),
                            'action' => 'tracking_event_added',
                            'note' => $data['description'] ?? 'Tracking event added',
                            'payload' => $data,
                        ]);
                    }),
               Action::make('overrideStatus')
                    ->label('Override Status')
                    ->color('warning')
                    ->schema([
                        Forms\Components\Select::make('fulfillment_status')->options([
                            'pending' => 'Pending',
                            'awaiting_fulfillment' => 'Awaiting',
                            'fulfilling' => 'Ordered',
                            'fulfilled' => 'Delivered',
                            'failed' => 'Failed',
                        ])->required(),
                        Forms\Components\Textarea::make('note')->rows(2)->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update(['fulfillment_status' => $data['fulfillment_status']]);
                        OrderAuditLog::create([
                            'order_id' => $record->order_id,
                            'user_id' => auth()->id(),
                            'action' => 'fulfillment_override',
                            'note' => $data['note'],
                            'payload' => ['status' => $data['fulfillment_status']],
                        ]);
                    }),
                Action::make('updateStatus')
                    ->label('Update Status')
                    ->icon('heroicon-o-check-circle')
                    ->schema([
                        Forms\Components\Select::make('fulfillment_status')->options([
                            'pending' => 'Pending',
                            'awaiting_fulfillment' => 'Awaiting',
                            'fulfilling' => 'Ordered',
                            'fulfilled' => 'Delivered',
                            'failed' => 'Failed',
                        ])->required(),
                        Forms\Components\Textarea::make('note')->rows(2)
                            ->required(fn ($get) => in_array($get('fulfillment_status'), ['failed'], true)),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update(['fulfillment_status' => $data['fulfillment_status']]);
                        OrderAuditLog::create([
                            'order_id' => $record->order_id,
                            'user_id' => auth()->id(),
                            'action' => 'fulfillment_status_updated',
                            'note' => $data['note'] ?? null,
                            'payload' => ['status' => $data['fulfillment_status']],
                        ]);
                    }),
                Action::make('ingestTrackingEvents')
                    ->label('Ingest Tracking JSON')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->schema([
                        Forms\Components\Textarea::make('events_json')
                            ->rows(5)
                            ->required()
                            ->helperText('Paste array of tracking events with status_code and occurred_at'),
                        Forms\Components\TextInput::make('tracking_number')
                            ->label('Tracking number')
                            ->required(),
                    ])
                    ->action(function ($record, array $data): void {
                        $tracking = app(TrackingService::class);
                        $events = json_decode($data['events_json'], true);

                        if (! is_array($events)) {
                            Notification::make()
                                ->title('Invalid JSON payload.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $shipment = $record->shipments()
                            ->where('tracking_number', $data['tracking_number'])
                            ->first();

                        if (! $shipment) {
                            $shipment = $tracking->recordShipment($record, [
                                'tracking_number' => $data['tracking_number'],
                                'carrier' => null,
                                'shipped_at' => now(),
                            ]);
                        }

                        $tracking->syncFromWebhook($shipment, $events);

                        OrderAuditLog::create([
                            'order_id' => $record->order_id,
                            'user_id' => auth()->id(),
                            'action' => 'tracking_ingested',
                            'note' => 'Tracking events ingested',
                            'payload' => ['tracking_number' => $data['tracking_number']],
                        ]);
                    }),
              Action::make('markDelivered')
                    ->label('Mark Delivered')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $shipment = $record->shipments()->latest('shipped_at')->first();
                        if ($shipment) {
                            $shipment->update(['delivered_at' => $shipment->delivered_at ?? now()]);
                        }

                        $record->update(['fulfillment_status' => 'fulfilled']);

                        // If all items are fulfilled, roll the order status up.
                        $order = $record->order;
                        if ($order->orderItems()->where('fulfillment_status', '!=', 'fulfilled')->doesntExist()) {
                            $order->update(['status' => 'fulfilled']);
                            event(new OrderDelivered($order));
                        }

                        OrderAuditLog::create([
                            'order_id' => $record->order_id,
                            'user_id' => auth()->id(),
                            'action' => 'marked_delivered',
                            'note' => 'Marked delivered by admin',
                            'payload' => ['order_item_id' => $record->id],
                        ]);
                    }),
            ])
            ->toolbarActions([]);
    }
}
