<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Filament;

use App\Domain\WooCommerce\Filament\WooCommerceSyncLogResource\Pages;
use App\Domain\WooCommerce\Jobs\SyncWooCustomerJob;
use App\Domain\WooCommerce\Jobs\SyncWooOrderJob;
use App\Domain\WooCommerce\Jobs\SyncWooProductJob;
use App\Domain\WooCommerce\Models\WooCommerceSyncLog;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WooCommerceSyncLogResource extends Resource
{
    protected static ?string $model = WooCommerceSyncLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|\UnitEnum|null $navigationGroup = 'Integrations';

    protected static ?string $navigationLabel = 'WC Sync Logs';

    protected static ?int $navigationSort = 85;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entity_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'product' => 'info',
                        'order' => 'warning',
                        'customer' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('entity_id')
                    ->label('Entity ID')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('action')
                    ->label('Action'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success', 'synced' => 'success',
                        'failed' => 'danger',
                        'warning', 'skipped' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('error')
                    ->label('Error')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('retry')
                    ->label('Retry')
                    ->action(function (WooCommerceSyncLog $record): void {
                        $job = match ($record->entity_type) {
                            'product' => new SyncWooProductJob($record->entity_id),
                            'order' => new SyncWooOrderJob($record->entity_id),
                            'customer' => new SyncWooCustomerJob($record->entity_id),
                            default => null,
                        };

                        if ($job) {
                            dispatch($job);
                            $record->update(['status' => 'retrying']);
                        }
                    })
                    ->visible(fn (WooCommerceSyncLog $record): bool => $record->status === 'failed'),

                DeleteAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWooCommerceSyncLogs::route('/'),
        ];
    }
}
