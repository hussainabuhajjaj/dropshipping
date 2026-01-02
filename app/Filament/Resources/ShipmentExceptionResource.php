<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Orders\Models\Shipment;
use App\Domain\Orders\Models\ShipmentException;
use App\Enums\ShipmentExceptionCode;
use App\Enums\ShipmentExceptionResolutionCode;
use App\Filament\Resources\ShipmentExceptionResource\Pages;
use BackedEnum;
use Filament\Actions\Action as ActionsAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ShipmentExceptionResource extends Resource
{
    protected static ?string $model = Shipment::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static UnitEnum|string|null $navigationGroup = 'Fulfillment';
    protected static ?int $navigationSort = 25;
    protected static ?string $navigationLabel = 'Shipment Exceptions';

    public static function getLabel(): ?string
    {
        return 'Shipment Exceptions';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Shipment Exceptions';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
                Section::make('Shipment Details')
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('Shipment ID')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('orderItem.order.number')
                            ->label('Order #')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('tracking_number')
                            ->label('Tracking #')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('carrier')
                            ->label('Carrier')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('logistic_name')
                            ->label('Shipping Method')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2),

                Section::make('Exception Details')
                    ->schema([
                        Forms\Components\Select::make('exception_code')
                            ->label('Exception Type')
                            ->options(ShipmentExceptionCode::labels())
                            ->disabled()
                            ->dehydrated(false),

                        // DateTimeInput::make('exception_at')
                        //     ->label('Exception Occurred')
                        //     ->disabled()
                        //     ->dehydrated(false),

                        Forms\Components\Textarea::make('exception_reason')
                            ->label('Exception Reason')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(1),

                Section::make('Resolution')
                    ->schema([
                        Forms\Components\Select::make('resolution_code')
                            ->label('Resolution Type')
                            ->options(ShipmentExceptionResolutionCode::labels())
                            ->visible(fn ($record) => $record && ! $record->isResolved())
                            ->required(fn ($record) => $record && ! $record->isResolved()),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->rows(3)
                            ->visible(fn ($record) => $record && ! $record->isResolved())
                            ->required(fn ($record) => $record && ! $record->isResolved()),

                        // Forms\Components\DateTimeInput::make('resolved_at')
                        //     ->label('Resolved At')
                        //     ->disabled()
                        //     ->dehydrated(false)
                        //     ->visible(fn ($record) => $record && $record->isResolved()),

                        Forms\Components\TextInput::make('resolved_by_user.name')
                            ->label('Resolved By')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($record) => $record && $record->isResolved()),
                    ])->columns(2),

                Section::make('Tracking Status')
                    ->schema([
                        Forms\Components\TextInput::make('shipped_at')
                            ->label('Shipped At')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($state) => $state?->format('M d, Y H:i')),

                        Forms\Components\TextInput::make('delivered_at')
                            ->label('Delivered At')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($state) => $state ? $state->format('M d, Y H:i') : 'Not delivered'),

                        TextColumn::make('is_at_risk')
                            ->label('At Risk')
                            ->visible(fn ($record) => $record)
                            ->color(fn ($state) => $state ? 'danger' : 'success')->badge(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('orderItem.order.number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Tracking #')
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('carrier')
                    ->label('Carrier')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('exception_code')
                    ->label('Exception Type')
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                    ->color(fn ($state) => match ($state?->severity()) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('exception_reason')
                    ->label('Reason')
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('exception_at')
                    ->label('Reported')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_at_risk')
                    ->label('At Risk')
                    ->boolean()
                    ->sortable(),

                BadgeColumn::make('resolved_at')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => $state ? 'Resolved' : 'Open')
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('exception_code')
                    ->label('Exception Type')
                    ->options(ShipmentExceptionCode::labels()),

                Filter::make('has_exception')
                    ->label('Has Exception')
                    ->query(fn (Builder $query) => $query->whereNotNull('exception_code')),

                Filter::make('unresolved')
                    ->label('Unresolved Only')
                    ->query(fn (Builder $query) => $query->whereNull('resolved_at')),

                Filter::make('at_risk')
                    ->label('At Risk Only')
                    ->query(fn (Builder $query) => $query->where('is_at_risk', true)),

                Filter::make('exception_at')
                    ->label('Exception Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q) => $q->whereDate('exception_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn ($q) => $q->whereDate('exception_at', '<=', $data['until']));
                    }),
            ])
            ->actions([
               ViewAction::make(),

                ActionsAction::make('resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record && ! $record->isResolved())
                    ->form([
                        Forms\Components\Select::make('resolution_code')
                            ->label('Resolution Type')
                            ->options(ShipmentExceptionResolutionCode::labels())
                            ->required(),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            /** @var \App\Models\User|null $user */
                            $user = auth()->user();
                            $record->markExceptionResolved(
                                $data['resolution_code'],
                                $data['admin_notes'],
                                $user
                            );

                            Notification::make()
                                ->title('Exception Resolved')
                                ->body('Shipment exception marked as resolved.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                ActionsAction::make('add_notes')
                    ->icon('heroicon-o-pencil')
                    ->color('info')
                    ->visible(fn ($record) => $record && ! $record->isResolved())
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->rows(3)
                            ->default(fn ($record) => $record->admin_notes ?? '')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $record->update(['admin_notes' => $data['admin_notes']]);

                            Notification::make()
                                ->title('Notes Updated')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                ActionsAction::make('mark_at_risk')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->visible(fn ($record) => $record && ! $record->isAtRisk() && ! $record->hasException())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $record->markAsAtRisk($data['reason'] ?? 'Manually marked by admin');

                            Notification::make()
                                ->title('Marked as At-Risk')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('exception_at', 'desc')
            ->query(fn () => Shipment::whereNotNull('exception_code')->orWhere('is_at_risk', true));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShipmentExceptions::route('/'),
            'view' => Pages\ViewShipmentException::route('/{record}'),
        ];
    }
}
