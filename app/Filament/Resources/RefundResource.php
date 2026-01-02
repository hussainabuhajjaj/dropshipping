<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Orders\Models\Refund;
use App\Domain\Orders\Services\RefundService;
use App\Filament\Resources\RefundResource\Pages;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
                Section::make('Refund Information')
                    ->schema([
                        Forms\Components\TextInput::make('order.number')
                            ->label('Order #')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\Select::make('reason_code')
                            ->label('Reason')
                            ->options(Refund::reasonCodes())
                            ->disabled(fn ($record) => $record?->status !== Refund::STATUS_PENDING)
                            ->dehydrated(false),
                        
                        Forms\Components\Textarea::make('customer_reason')
                            ->label('Customer Reason')
                            ->rows(2)
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2),

                Section::make('Processing')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(Refund::statuses())
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\TextInput::make('approvedByUser.name')
                            ->label('Approved By')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\DateTimePicker::make('approved_at')
                            ->label('Approved At')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\TextInput::make('transaction_id')
                            ->label('Transaction ID')
                            ->disabled(fn ($record) => $record?->status !== Refund::STATUS_COMPLETED)
                            ->dehydrated(false),
                    ])->columns(2),

                Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->rows(3)
                            ->disabled(fn ($record) => $record?->status !== Refund::STATUS_PENDING),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('order.number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('orderItem.product.name')
                    ->label('Item')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('reason_code')
                    ->label('Reason')
                    ->formatStateUsing(fn ($state) => Refund::reasonCodes()[$state] ?? $state)
                    ->colors([
                        'gray',
                    ]),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => Refund::STATUS_PENDING,
                        'info' => Refund::STATUS_APPROVED,
                        'success' => Refund::STATUS_COMPLETED,
                        'danger' => Refund::STATUS_REJECTED,
                        'warning' => Refund::STATUS_CANCELLED,
                    ])
                    ->formatStateUsing(fn ($state) => Refund::statuses()[$state] ?? $state),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('approved_at')
                    ->label('Approved')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Refund::statuses()),
                
                Tables\Filters\SelectFilter::make('reason_code')
                    ->label('Reason')
                    ->options(Refund::reasonCodes()),
            ])
            ->actions([
                ViewAction::make(),
                
                Action::make('approve')
                  
                    ->visible(fn ($record) => $record->canBeApproved())
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $service = app(RefundService::class);
                            $service->approveRefund($record);
                            
                            if ($data['admin_notes']) {
                                $record->update(['admin_notes' => $data['admin_notes']]);
                            }
                            
                            Notification::make()
                                ->title('Refund Approved')
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
                
                Action::make('reject')
                    ->color('danger')
                    ->visible(fn ($record) => $record->canBeRejected())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $service = app(RefundService::class);
                            $service->rejectRefund($record, $data['reason'] ?? '');
                            
                            Notification::make()
                                ->title('Refund Rejected')
                                ->warning()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                
                Action::make('complete')
                    ->color('info')
                    ->visible(fn ($record) => $record->canBeProcessed())
                    ->form([
                        Forms\Components\TextInput::make('transaction_id')
                            ->label('Transaction ID')
                            ->required(),
                        
                        Forms\Components\Textarea::make('gateway_response')
                            ->label('Gateway Response (JSON)')
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $gatewayResponse = null;
                            if ($data['gateway_response']) {
                                $gatewayResponse = json_decode($data['gateway_response'], true);
                            }
                            
                            $service = app(RefundService::class);
                            $service->completeRefund($record, $data['transaction_id'], $gatewayResponse);
                            
                            Notification::make()
                                ->title('Refund Completed')
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
                
                Action::make('cancel')
                    ->color('warning')
                    ->visible(fn ($record) => $record->canBeCancelled())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Cancellation Reason')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $service = app(RefundService::class);
                            $service->cancelRefund($record, $data['reason'] ?? '');
                            
                            Notification::make()
                                ->title('Refund Cancelled')
                                ->warning()
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
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRefunds::route('/'),
            'view' => Pages\ViewRefund::route('/{record}'),
        ];
    }
}



