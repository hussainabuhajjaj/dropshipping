<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WhatsAppOrderIntentResource\Pages;
use App\Models\WhatsAppOrderIntent;
use App\Services\Checkout\WhatsAppOrderIntentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppOrderIntentResource extends BaseResource
{
    protected static ?string $model = WhatsAppOrderIntent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static string|\UnitEnum|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 11;
    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'convertedOrder']))
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'converted' => 'success',
                        'expired' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('intent_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('channel')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->sortable(),
                Tables\Columns\TextColumn::make('grand_total')
                    ->money(fn (WhatsAppOrderIntent $record) => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.email')
                    ->label('Customer')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('convertedOrder.number')
                    ->label('Order #')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'converted' => 'Converted',
                        'expired' => 'Expired',
                    ]),
                SelectFilter::make('channel')
                    ->options([
                        'web' => 'Web',
                        'mobile' => 'Mobile',
                    ]),
                SelectFilter::make('intent_type')
                    ->options([
                        'product' => 'Product',
                        'cart' => 'Cart',
                    ]),
                Filter::make('guest_only')
                    ->label('Guests only')
                    ->query(fn (Builder $query) => $query->whereNull('customer_id')),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('expire')
                    ->requiresConfirmation()
                    ->visible(fn (WhatsAppOrderIntent $record) => $record->status === WhatsAppOrderIntent::STATUS_PENDING)
                    ->action(fn (WhatsAppOrderIntent $record) => $record->markExpired('manually_expired')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Intent')
                ->columns(3)
                ->schema([
                    TextEntry::make('reference')->copyable(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('intent_type')->badge(),
                    TextEntry::make('channel')->badge(),
                    TextEntry::make('customer.email')->label('Customer')->placeholder('Guest'),
                    TextEntry::make('phone')->placeholder('—'),
                    TextEntry::make('currency'),
                    TextEntry::make('items_count'),
                    TextEntry::make('grand_total')->money(fn (WhatsAppOrderIntent $record) => $record->currency),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('expires_at')->dateTime(),
                    TextEntry::make('convertedOrder.number')->label('Converted order')->placeholder('—'),
                ]),
            Section::make('Snapshot Lines')
                ->schema([
                    RepeatableEntry::make('snapshot.lines')
                        ->schema([
                            TextEntry::make('product_name')->label('Product'),
                            TextEntry::make('variant_name')->label('Variant')->placeholder('Default'),
                            TextEntry::make('quantity'),
                            TextEntry::make('unit_price')->money(fn (WhatsAppOrderIntent $record) => $record->currency),
                            TextEntry::make('line_total')->money(fn (WhatsAppOrderIntent $record) => $record->currency),
                        ])
                        ->columns(5),
                ]),
            Section::make('Snapshot Totals')
                ->columns(5)
                ->schema([
                    TextEntry::make('snapshot.totals.subtotal')->money(fn (WhatsAppOrderIntent $record) => $record->currency),
                    TextEntry::make('snapshot.totals.shipping_total')->money(fn (WhatsAppOrderIntent $record) => $record->currency),
                    TextEntry::make('snapshot.totals.discount_total')->money(fn (WhatsAppOrderIntent $record) => $record->currency),
                    TextEntry::make('snapshot.totals.tax_total')->money(fn (WhatsAppOrderIntent $record) => $record->currency),
                    TextEntry::make('snapshot.totals.grand_total')->money(fn (WhatsAppOrderIntent $record) => $record->currency),
                ]),
            Section::make('Metadata')
                ->schema([
                    KeyValueEntry::make('snapshot.customer')->label('Customer snapshot'),
                    KeyValueEntry::make('snapshot.source')->label('Source'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsAppOrderIntents::route('/'),
            'view' => Pages\ViewWhatsAppOrderIntent::route('/{record}'),
        ];
    }
}
