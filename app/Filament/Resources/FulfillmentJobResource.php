<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\FulfillmentJobResource\Pages;
use App\Filament\Resources\FulfillmentJobResource\RelationManagers\FulfillmentAttemptsRelationManager;
use App\Models\Fulfillment;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Jobs\DispatchFulfillmentJob;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use App\Filament\Resources\BaseResource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FulfillmentJobResource extends BaseResource
{
    protected static ?string $model = Fulfillment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';
    protected static string|\UnitEnum|null $navigationGroup = 'Fulfillment';
    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('orderItem.order.number')->label('Order #')->searchable(),
                Tables\Columns\TextColumn::make('orderItem.id')->label('Item ID')->sortable(),
                Tables\Columns\TextColumn::make('provider.name')->label('Provider')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('external_reference')->label('Reference')->limit(20),
                Tables\Columns\TextColumn::make('last_error')->limit(40)->toggleable(),
                Tables\Columns\TextColumn::make('dispatched_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('fulfilled_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'in_progress' => 'In progress',
                    'succeeded' => 'Succeeded',
                    'failed' => 'Failed',
                    'needs_action' => 'Needs action',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (Fulfillment $record): void {
                        DispatchFulfillmentJob::dispatch($record->order_item_id);
                    }),
              Action::make('reassignProvider')
                    ->label('Assign Provider')
                    ->icon('heroicon-o-arrows-right-left')
                    ->schema([
                        Forms\Components\Select::make('fulfillment_provider_id')
                            ->label('Provider')
                            ->options(fn () => FulfillmentProvider::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Fulfillment $record, array $data): void {
                        $record->orderItem()->update(['fulfillment_provider_id' => $data['fulfillment_provider_id']]);
                        $record->update(['status' => 'pending']);
                    }),
                ])
            ->toolbarActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Fulfillment')
                ->schema([
                    TextEntry::make('orderItem.order.number')->label('Order #'),
                    TextEntry::make('orderItem.id')->label('Item ID'),
                    TextEntry::make('provider.name')->label('Provider'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('external_reference')->label('Reference'),
                    TextEntry::make('last_error')->label('Last error'),
                    TextEntry::make('dispatched_at')->dateTime(),
                    TextEntry::make('fulfilled_at')->dateTime(),
                ])->columns(3),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            FulfillmentAttemptsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFulfillmentJobs::route('/'),
            'view' => Pages\ViewFulfillmentJob::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}



