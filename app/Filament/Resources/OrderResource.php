<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Fulfillment\Models\FulfillmentJob;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderAuditLog;
use App\Domain\Orders\Models\Shipment;
use App\Domain\Orders\Services\RefundService;
use App\Domain\Orders\Services\TrackingService;
use App\Domain\Observability\EventLogger;
use App\Enums\ShipmentExceptionCode;
use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers\OrderItemsRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\OrderEventsRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\PaymentEventsRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\ShipmentsRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\TrackingEventsRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\OrderAuditLogsRelationManager;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Jobs\DispatchFulfillmentJob;
use BackedEnum;
use Filament\Actions\Action as ActionsAction;
use Filament\Actions\BulkAction as ActionsBulkAction;
use Filament\Actions\BulkActionGroup as ActionsBulkActionGroup;
use Filament\Actions\DeleteBulkAction as ActionsDeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as ComponentsSection;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 10;
    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            //...existing code...
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->url(fn(Order $record) => static::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(false)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable(['guest_name', 'email'])
                    ->state(fn (Order $record) => $record->is_guest
                        ? ($record->guest_name . ' (Guest)')
                        : ($record->shippingAddress?->name ?? '—')
                    ),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Fulfillment')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'pending' => 'gray',
                        'paid' => 'info',
                        'fulfilling' => 'warning',
                        'fulfilled' => 'success',
                        'cancelled' => 'danger',
                        'refunded' => 'danger',
                        default => 'secondary',
                    })
                    ->icon(fn (?string $state) => match ($state) {
                        'fulfilled' => 'heroicon-o-check-badge',
                        'cancelled' => 'heroicon-o-x-circle',
                        'refunded' => 'heroicon-o-arrow-uturn-left',
                        default => null,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'paid' => 'success',
                        'unpaid' => 'danger',
                        'refunded' => 'warning',
                        default => 'secondary',
                    })
                    ->icon(fn (?string $state) => match ($state) {
                        'paid' => 'heroicon-o-currency-dollar',
                        'refunded' => 'heroicon-o-arrow-uturn-left',
                        default => null,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->money(fn (Order $record) => $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('placed_at')
                    ->label('Placed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('audit_log_action')
                    ->label('Audit Log Action')
                    ->relationship('orderAuditLogs', 'action')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'paid' => 'Paid',
                        'refunded' => 'Refunded',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Fulfillment status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'fulfilling' => 'Fulfilling',
                        'fulfilled' => 'Fulfilled',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ]),

                Tables\Filters\SelectFilter::make('fulfillment_provider')
                    ->label('Fulfillment provider')
                    ->relationship('orderItems.fulfillmentProvider', 'name'),

                Tables\Filters\Filter::make('customer_email')
                    ->form([
                        Forms\Components\TextInput::make('value')
                            ->label('Customer Email')
                            ->placeholder('example@domain.com'),
                    ])
                    ->query(fn ($query, array $data) => $query->when(
                        filled($data['value'] ?? null),
                        fn ($q) => $q->where('email', 'like', '%' . $data['value'] . '%')
                    )),

                Tables\Filters\SelectFilter::make('shipping_country')
                    ->label('Shipping Country')
                    ->relationship('shippingAddress', 'country'),

                Tables\Filters\Filter::make('product_sku')
                    ->form([
                        Forms\Components\TextInput::make('value')
                            ->label('Product SKU')
                            ->placeholder('SKU...'),
                    ])
                    ->query(fn ($query, array $data) => $query->when(
                        filled($data['value'] ?? null),
                        fn ($q) => $q->whereHas('orderItems', fn ($qq) => $qq->where('sku', 'like', '%' . $data['value'] . '%'))
                    )),

                Tables\Filters\Filter::make('recent')
                    ->label('Last 7 Days')
                    ->query(fn ($query) => $query->where('placed_at', '>=', now()->subDays(7))),

                Tables\Filters\Filter::make('unfulfilled')
                    ->label('Unfulfilled')
                    ->query(fn ($query) => $query->where('status', '!=', 'fulfilled')),

                Tables\Filters\Filter::make('high_value')
                    ->label('High Value')
                    ->query(fn ($query) => $query->where('grand_total', '>=', 500)),

                Tables\Filters\Filter::make('placed_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('placed_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('placed_at', '<=', $date));
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->label('View Details')
                    ->icon('heroicon-o-eye'),

                ActionsAction::make('dispatch_all')
                    ->label('Dispatch All')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Dispatch All Order Items')
                    ->modalDescription(fn (Order $record) =>
                        "This will dispatch all items in order {$record->number} to their fulfillment providers."
                    )
                    ->visible(fn (Order $record) => $record->orderItems()->where('fulfillment_status', '!=', 'fulfilling')->exists())
                    ->action(function (Order $record) {
                        $items = $record->orderItems()->where('fulfillment_status', '!=', 'fulfilling')->get();
                        foreach ($items as $item) {
                            \App\Jobs\DispatchFulfillmentJob::dispatch($item->id);
                            $item->update(['fulfillment_status' => 'fulfilling']);
                            \App\Domain\Orders\Models\OrderAuditLog::create([
                                'order_id' => $record->id,
                                'user_id' => auth()->id(),
                                'action' => 'fulfillment_dispatched',
                                'note' => 'Dispatched to provider (bulk)',
                                'payload' => ['order_item_id' => $item->id],
                            ]);
                        }
                        Notification::make()
                            ->title('All items dispatched')
                            ->success()
                            ->send();
                    }),

                ActionsAction::make('pay_cj')
                    ->label('Pay CJ Balance')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Pay CJ Balance')
                    ->modalDescription(fn (Order $record) => 
                        "This will pay CJ Dropshipping {$record->cj_amount_due} {$record->currency} for order {$record->number}"
                    )
                    ->visible(fn (Order $record) => 
                        $record->payment_status === 'paid' &&
                        $record->cj_order_id &&
                        $record->cj_order_status === 'confirmed' &&
                        $record->cj_payment_status !== 'paid'
                    )
                    ->action(function (Order $record) {
                        \App\Jobs\PayCJBalanceJob::dispatch($record->id);
                        Notification::make()
                            ->title('CJ payment queued')
                            ->body("Order {$record->number} queued for CJ payment")
                            ->success()
                            ->send();
                    }),

                ActionsAction::make('retry_cj_payment')
                    ->label('Retry CJ Payment')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Retry CJ Payment')
                    ->modalDescription(fn (Order $record) => 
                        "Attempt #" . ($record->cj_payment_attempts + 1) . ": Retry payment for order {$record->number}"
                    )
                    ->visible(fn (Order $record) => 
                        $record->cj_payment_status === 'failed' &&
                        $record->cj_payment_attempts < 5
                    )
                    ->action(function (Order $record) {
                        $record->update(['cj_payment_status' => 'pending']);
                        \App\Jobs\PayCJBalanceJob::dispatch($record->id);
                        $nextAttempt = $record->cj_payment_attempts + 1;
                        Notification::make()
                            ->title('CJ payment retry queued')
                            ->body("Order {$record->number} queued for CJ payment retry (attempt #{$nextAttempt})")
                            ->warning()
                            ->send();
                    }),

               ActionsAction::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record) => $record->payment_status === 'paid' && $record->status !== 'refunded')
                    ->action(fn (Order $record) => $record->refund()),

                ActionsAction::make('mark_fulfilled')
                    ->label('Mark as Fulfilled')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Order $record) => $record->status !== 'fulfilled')
                    ->action(fn (Order $record) => $record->markAsFulfilled()),
            ])
            ->headerActions([
                // PageExportAction::make()
                //     ->label('Export Orders')
                //     ->fileName(fn () => 'orders-' . now()->format('Ymd-His') . '.csv'),
            ])
            ->toolbarActions([
                ActionsBulkActionGroup::make([
                  ActionsDeleteBulkAction::make(),

                   ActionsBulkAction::make('bulk_mark_fulfilled')
                        ->label('Mark as Fulfilled')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->markAsFulfilled()),

                    ActionsBulkAction::make('bulk_refund')
                        ->label('Refund')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->refund()),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            ComponentsSection::make('Order')
                ->schema([
                    TextEntry::make('number')->label('Order #')->copyable(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('payment_status')->badge(),
                    TextEntry::make('grand_total')->money(fn (Order $record) => $record->currency),
                    TextEntry::make('placed_at')->dateTime(),
                    TextEntry::make('payment_method')->label('Payment Method'),
                    TextEntry::make('shipping_method')->label('Shipping Method'),
                    TextEntry::make('order_notes')->label('Order Notes'),
                    TextEntry::make('tags')
                        ->label('Tags')
                        ->state(fn (Order $record) => collect($record->tags ?? [])->implode(', ')),
                    TextEntry::make('internal_comments')->label('Internal Comments'),
                ])
                ->columns(5),

            ComponentsSection::make('Customer')
                ->schema([
                    TextEntry::make('guest_or_customer_name')
                        ->label('Name')
                        ->state(fn (Order $record) => $record->is_guest
                            ? ($record->guest_name . ' (Guest)')
                            : ($record->shippingAddress?->name ?? '—')
                        ),
                    TextEntry::make('email')->copyable(),
                    TextEntry::make('phone_number')
                        ->label('Phone')
                        ->copyable()
                        ->state(fn (Order $record) => $record->is_guest
                            ? $record->guest_phone
                            : ($record->shippingAddress?->phone ?? '—')
                        ),
                    TextEntry::make('shippingAddress.line1')->label('Address')->copyable(),
                    TextEntry::make('shippingAddress.city')->label('City')->copyable(),
                    TextEntry::make('shippingAddress.country')->label('Country'),
                ])
                ->columns(2),

            ComponentsSection::make('Shipping')
                ->schema([
                    TextEntry::make('shippingAddress.name')->label('Name')->copyable(),
                    TextEntry::make('shippingAddress.phone')->label('Phone')->copyable(),
                    TextEntry::make('shippingAddress.line1')->label('Line 1')->copyable(),
                    TextEntry::make('shippingAddress.line2')->label('Line 2')->copyable(),
                    TextEntry::make('shippingAddress.city')->label('City')->copyable(),
                    TextEntry::make('shippingAddress.state')->label('State')->copyable(),
                    TextEntry::make('shippingAddress.postal_code')->label('Postal')->copyable(),
                    TextEntry::make('shippingAddress.country')->label('Country'),
                ])
                ->columns(4),

            ComponentsSection::make('Billing')
                ->schema([
                    TextEntry::make('billingAddress.name')->label('Name')->copyable(),
                    TextEntry::make('billingAddress.line1')->label('Line 1')->copyable(),
                    TextEntry::make('billingAddress.city')->label('City'),
                    TextEntry::make('billingAddress.country')->label('Country'),
                ])
                ->columns(4),

            ComponentsSection::make('Order Items Summary')
                ->schema([
                    TextEntry::make('order_items_summary')
                        ->label('Items')
                        ->state(fn (Order $record) => collect($record->orderItems)
                            ->map(fn ($item) => $item->sku . ' × ' . $item->quantity)
                            ->implode(', ')
                        ),
                ])
                ->columns(1),

            ComponentsSection::make('Timeline')
                ->schema([
                    TextEntry::make('status_history')
                        ->label('Status History')
                        ->state(fn (Order $record) => collect($record->status_history ?? [])->implode(' → ')),
                ])
                ->columns(1),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            OrderItemsRelationManager::class,
            ShipmentsRelationManager::class,
            PaymentsRelationManager::class,
            OrderEventsRelationManager::class,
            PaymentEventsRelationManager::class,
            OrderAuditLogsRelationManager::class,
            \App\Filament\Resources\OrderResource\RelationManagers\FulfillmentEventsRelationManager::class,
            \App\Filament\Resources\OrderResource\RelationManagers\CustomerProfileRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
