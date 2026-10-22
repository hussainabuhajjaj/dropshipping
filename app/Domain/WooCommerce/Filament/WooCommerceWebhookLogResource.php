<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Filament;

use App\Domain\WooCommerce\Filament\WooCommerceWebhookLogResource\Pages;
use App\Domain\WooCommerce\Models\WooCommerceWebhookLog;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WooCommerceWebhookLogResource extends Resource
{
    protected static ?string $model = WooCommerceWebhookLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Integrations';

    protected static ?string $navigationLabel = 'WC Webhook Logs';

    protected static ?int $navigationSort = 86;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'received' => 'warning',
                        'processed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('webhook_id')
                    ->label('Webhook ID')
                    ->searchable(),

                TextColumn::make('last_error')
                    ->label('Error')
                    ->limit(50),

                TextColumn::make('processed_at')
                    ->label('Processed')
                    ->dateTime(),

                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWooCommerceWebhookLogs::route('/'),
        ];
    }
}
