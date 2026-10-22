<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use BackedEnum;
use Filament\Forms;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action as TableAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use App\Domain\Fulfillment\Services\SupplierPerformanceService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup as ActionsBulkActionGroup;
use Filament\Actions\DeleteBulkAction as ActionsDeleteBulkAction;
use Filament\Actions\EditAction as ActionsEditAction;
use Filament\Schemas\Components\Section;
use UnitEnum;

class SupplierResource extends BaseResource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';
    protected static string|UnitEnum|null $navigationGroup = 'Suppliers';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
           Section::make('Supplier')
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\Select::make('type')
                        ->options([
                            'aliexpress' => 'AliExpress',
                            'cj' => 'CJ',
                            'private' => 'Private',
                            'local' => 'Local',
                        ])->required(),
                    Forms\Components\TextInput::make('code')
                        ->helperText('Internal code')
                        ->required(),
                    Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
                    Forms\Components\Toggle::make('is_blacklisted')->label('Blacklisted')->helperText('Prevents new orders'),
                ])->columns(2),
          Section::make('Contact')
                ->schema([
                    Forms\Components\KeyValue::make('contact_info')->keyLabel('Field')->valueLabel('Value'),
                    Forms\Components\Textarea::make('notes')->rows(3),
                ])->columns(1),
            Section::make('Integration')
                ->schema([
                    Forms\Components\TextInput::make('driver_class')
                        ->label('Driver class')
                        ->default(\App\Domain\Fulfillment\Strategies\ManualFulfillmentStrategy::class)
                        ->required(),
                    Forms\Components\TextInput::make('retry_limit')->numeric()->default(3),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\IconColumn::make('is_blacklisted')->boolean()->label('Blacklisted'),
                Tables\Columns\TextColumn::make('metrics.fulfilled_count')
                    ->label('Fulfilled')
                    ->sortable()
                    ->default('—'),
                Tables\Columns\TextColumn::make('metrics.failed_count')
                    ->label('Failed')
                    ->sortable()
                    ->default('—'),
                Tables\Columns\TextColumn::make('metrics.average_lead_time_days')
                    ->label('Avg lead (d)')
                    ->sortable()
                    ->default('—'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TernaryFilter::make('is_blacklisted'),
            ])
            ->recordActions([
                ActionsEditAction::make(),
               Action::make('toggleActive')
                    ->label('Activate/Deactivate')
                    ->action(fn (Supplier $record) => $record->update(['is_active' => ! $record->is_active])),
                Action::make('toggleBlacklist')
                    ->label('Toggle Blacklist')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (Supplier $record) => $record->update(['is_blacklisted' => ! $record->is_blacklisted])),
                Action::make('refreshMetrics')
                    ->label('Refresh Metrics')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Supplier $record) {
                        app(SupplierPerformanceService::class)->refreshForProvider($record);
                    }),
            ])
            ->toolbarActions([
                ActionsBulkActionGroup::make([
                    BulkAction::make('refreshMetrics')
                        ->label('Refresh Metrics')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function ($records) {
                            $service = app(SupplierPerformanceService::class);
                            $records->each(fn ($record) => $service->refreshForProvider($record));
                        }),
                    ActionsDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}



