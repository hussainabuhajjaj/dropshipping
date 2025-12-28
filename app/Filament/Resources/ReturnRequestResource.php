<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ReturnRequestResource\Pages;
use App\Models\ReturnRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ReturnRequestResource extends BaseResource
{
    protected static ?string $model = ReturnRequest::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static UnitEnum|string|null $navigationGroup = 'Operations';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Return Request Details')
                ->schema([
                    Forms\Components\Select::make('order_id')
                        ->relationship('order', 'number')
                        ->searchable()
                        ->required()
                        ->disabled(fn ($record) => $record !== null),
                    Forms\Components\Select::make('order_item_id')
                        ->relationship('orderItem', 'id')
                        ->searchable()
                        ->disabled(fn ($record) => $record !== null),
                    Forms\Components\Select::make('customer_id')
                        ->relationship('customer', 'email')
                        ->searchable()
                        ->required()
                        ->disabled(fn ($record) => $record !== null),
                    Forms\Components\Select::make('status')
                        ->options([
                            'requested' => 'Requested',
                            'approved' => 'Approved',
                            'received' => 'Received',
                            'rejected' => 'Rejected',
                            'refunded' => 'Refunded',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('reason')
                        ->label('Return Reason')
                        ->maxLength(120),
                    Forms\Components\Textarea::make('notes')
                        ->label('Customer Notes')
                        ->rows(3),
                ])
                ->columns(2),
            Section::make('Approval Information')
                ->schema([
                    Forms\Components\Select::make('approved_by')
                        ->relationship('approver', 'name')
                        ->disabled()
                        ->visible(fn ($record) => $record?->approved_by !== null),
                    Forms\Components\DateTimePicker::make('approved_at')
                        ->label('Approved At')
                        ->disabled()
                        ->visible(fn ($record) => $record?->approved_at !== null),
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('Rejection Reason')
                        ->rows(2)
                        ->disabled()
                        ->visible(fn ($record) => $record?->rejection_reason !== null),
                    Forms\Components\TextInput::make('return_label_url')
                        ->label('Return Label URL')
                        ->url()
                        ->disabled(fn ($record) => $record?->status !== 'requested')
                        ->visible(fn ($record) => $record?->return_label_url !== null || $record?->status === 'approved'),
                ])
                ->columns(2)
                ->visible(fn ($record) => $record !== null && $record->status !== 'requested'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.number')->label('Order')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.email')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'requested' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'received' => 'info',
                        'refunded' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')->toggleable()->limit(30),
                Tables\Columns\TextColumn::make('approver.name')->label('Approved By')->toggleable(),
                Tables\Columns\TextColumn::make('approved_at')->label('Approved At')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Requested At')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'requested' => 'Requested',
                    'approved' => 'Approved',
                    'received' => 'Received',
                    'rejected' => 'Rejected',
                    'refunded' => 'Refunded',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('viewOrder')
                    ->label('View order')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (ReturnRequest $record) => route('filament.admin.resources.orders.view', $record->order_id))
                    ->openUrlInNewTab(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ReturnRequest $record) => $record->canBeApproved())
                    ->requiresConfirmation()
                    ->modalHeading('Approve Return Request')
                    ->modalDescription('Are you sure you want to approve this return request?')
                    ->form([
                        Forms\Components\Toggle::make('generate_label')
                            ->label('Auto-generate return shipping label')
                            ->default(true)
                            ->helperText('Automatically generate a return label for the customer'),
                        Forms\Components\TextInput::make('return_label_url')
                            ->label('Or provide custom label URL')
                            ->url()
                            ->placeholder('https://...')
                            ->helperText('Leave empty to auto-generate, or provide your own label URL'),
                    ])
                    ->action(function (ReturnRequest $record, array $data) {
                        $labelUrl = $data['return_label_url'] ?? null;
                        
                        // Auto-generate label if requested and no custom URL provided
                        if (($data['generate_label'] ?? false) && empty($labelUrl)) {
                            try {
                                $labelService = app(\App\Services\ReturnLabelService::class);
                                $record->update(['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => now()]);
                                $labelData = $labelService->generateLabel($record);
                                
                                if ($labelData) {
                                    $labelUrl = $labelData['label_url'];
                                    
                                    Notification::make()
                                        ->success()
                                        ->title('Return Approved with Label')
                                        ->body('Return label generated: ' . ($labelData['tracking_number'] ?? 'N/A'))
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->warning()
                                        ->title('Return Approved')
                                        ->body('Approved, but label generation failed. Please add label manually.')
                                        ->send();
                                }
                                
                                return;
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Error')
                                    ->body('Failed to generate label: ' . $e->getMessage())
                                    ->send();
                                return;
                            }
                        }
                        
                        // Use provided label URL or approve without label
                        $success = $record->approve(Auth::id(), $labelUrl);
                        
                        if ($success) {
                            Notification::make()
                                ->success()
                                ->title('Return Approved')
                                ->body('The return request has been approved successfully.')
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body('Could not approve this return request.')
                                ->send();
                        }
                    }),
                Action::make('generateLabel')
                    ->label('Generate Return Label')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->visible(fn (ReturnRequest $record) => $record->status === 'approved' && empty($record->return_label_url))
                    ->requiresConfirmation()
                    ->action(function (ReturnRequest $record) {
                        try {
                            $labelService = app(\App\Services\ReturnLabelService::class);
                            $labelData = $labelService->generateLabel($record);
                            
                            if ($labelData) {
                                Notification::make()
                                    ->success()
                                    ->title('Label Generated')
                                    ->body('Return label created: ' . ($labelData['tracking_number'] ?? 'N/A'))
                                    ->send();
                            } else {
                                Notification::make()
                                    ->warning()
                                    ->title('Generation Failed')
                                    ->body('Could not generate return label. Check logs.')
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body('Failed to generate label: ' . $e->getMessage())
                                ->send();
                        }
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ReturnRequest $record) => $record->canBeRejected())
                    ->requiresConfirmation()
                    ->modalHeading('Reject Return Request')
                    ->modalDescription('Please provide a reason for rejecting this return.')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3)
                            ->placeholder('Explain why this return is being rejected...'),
                    ])
                    ->action(function (ReturnRequest $record, array $data) {
                        $success = $record->reject(
                            Auth::id(),
                            $data['rejection_reason']
                        );
                        
                        if ($success) {
                            Notification::make()
                                ->success()
                                ->title('Return Rejected')
                                ->body('The return request has been rejected.')
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body('Could not reject this return request.')
                                ->send();
                        }
                    }),
                Action::make('markReceived')
                    ->label('Mark Received')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('info')
                    ->visible(fn (ReturnRequest $record) => $record->status === 'approved')
                    ->requiresConfirmation()
                    ->action(function (ReturnRequest $record) {
                        $record->update(['status' => 'received']);
                        
                        Notification::make()
                            ->success()
                            ->title('Status Updated')
                            ->body('Return marked as received.')
                            ->send();
                    }),
                Action::make('markRefunded')
                    ->label('Mark Refunded')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('warning')
                    ->visible(fn (ReturnRequest $record) => in_array($record->status, ['received', 'approved'], true))
                    ->requiresConfirmation()
                    ->action(function (ReturnRequest $record) {
                        $record->update(['status' => 'refunded']);
                        
                        Notification::make()
                            ->success()
                            ->title('Status Updated')
                            ->body('Return marked as refunded.')
                            ->send();
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReturnRequests::route('/'),
            'create' => Pages\CreateReturnRequest::route('/create'),
            'edit' => Pages\EditReturnRequest::route('/{record}/edit'),
        ];
    }
}

