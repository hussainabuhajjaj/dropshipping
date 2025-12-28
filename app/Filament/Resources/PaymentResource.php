<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Filament\Resources\PaymentResource\RelationManagers\PaymentEventsRelationManager;
use App\Filament\Resources\PaymentResource\RelationManagers\PaymentWebhooksRelationManager;
use App\Models\Payment;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use App\Filament\Resources\BaseResource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Infrastructure\Payments\Paystack\PaystackRefundService;
use Illuminate\Support\Facades\Auth;

class PaymentResource extends BaseResource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static string|\UnitEnum|null $navigationGroup = 'Payments';
    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.number')->label('Order #')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('provider')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'refunded' => 'gray',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        'authorized' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')->money(fn ($record) => $record->currency)->sortable(),
                Tables\Columns\TextColumn::make('refunded_amount')
                    ->label('Refunded')
                    ->money(fn ($record) => $record->currency)
                    ->toggleable()
                    ->visible(fn ($record) => $record->refunded_amount > 0),
                Tables\Columns\TextColumn::make('refund_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'partial' => 'warning',
                        'full' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('provider_reference')->label('Reference')->limit(20)->toggleable(),
                Tables\Columns\TextColumn::make('paid_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'authorized' => 'Authorized',
                    'paid' => 'Paid',
                    'failed' => 'Failed',
                    'refunded' => 'Refunded',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('refund')
                    ->label('Process Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Payment $record) => 
                        $record->status === 'paid' && 
                        $record->provider === 'paystack' &&
                        ($record->refunded_amount < $record->amount)
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Process Refund')
                    ->modalDescription('Issue a refund for this payment. This action cannot be undone.')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Refund Amount')
                            ->numeric()
                            ->required()
                            ->prefix(fn (Payment $record) => $record->currency)
                            ->helperText(fn (Payment $record) => 
                                'Available to refund: ' . 
                                number_format($record->amount - $record->refunded_amount, 2) . 
                                ' ' . $record->currency
                            )
                            ->rules([
                                'required',
                                'numeric',
                                'min:0.01',
                            ]),
                        Forms\Components\Textarea::make('reason')
                            ->label('Refund Reason')
                            ->required()
                            ->rows(3)
                            ->placeholder('Explain why this payment is being refunded...'),
                    ])
                    ->action(function (Payment $record, array $data) {
                        try {
                            $refundService = app(PaystackRefundService::class);
                            $refundService->refund(
                                $record,
                                (float) $data['amount'],
                                $data['reason'],
                                Auth::id()
                            );
                            
                            Notification::make()
                                ->success()
                                ->title('Refund Processed')
                                ->body("Refund of {$data['amount']} {$record->currency} has been processed successfully.")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Refund Failed')
                                ->body('Error: ' . $e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Payment Details')
                ->schema([
                    TextEntry::make('order.number')->label('Order #')->copyable(),
                    TextEntry::make('provider')->badge(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('provider_reference')->label('Reference')->copyable(),
                    TextEntry::make('amount')->money(fn ($record) => $record->currency),
                    TextEntry::make('paid_at')->dateTime(),
                    TextEntry::make('created_at')->dateTime(),
                ])->columns(3),
            Section::make('Refund Information')
                ->schema([
                    TextEntry::make('refund_status')
                        ->badge()
                        ->color(fn ($state) => match ($state) {
                            'partial' => 'warning',
                            'full' => 'gray',
                            default => 'gray',
                        }),
                    TextEntry::make('refunded_amount')
                        ->money(fn ($record) => $record->currency),
                    TextEntry::make('refund_reference')
                        ->label('Refund Reference')
                        ->copyable(),
                    TextEntry::make('refund_reason')
                        ->label('Reason')
                        ->columnSpanFull(),
                    TextEntry::make('refunder.name')
                        ->label('Refunded By'),
                    TextEntry::make('refunded_at')
                        ->dateTime(),
                ])
                ->columns(3)
                ->visible(fn ($record) => $record->refunded_amount > 0),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            PaymentEventsRelationManager::class,
            PaymentWebhooksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}



