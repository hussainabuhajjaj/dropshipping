<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Orders\Models\ChargebackCase;
use App\Domain\Orders\Models\ChargebackEvidence;
use App\Enums\ChargebackStatus;
use App\Enums\ChargebackEvidenceType;
use App\Filament\Resources\ChargebackCaseResource\Pages;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ChargebackCaseResource extends Resource
{
    protected static ?string $model = ChargebackCase::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static UnitEnum|string|null $navigationGroup = 'Payment Management';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'case_number';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Case Information')
                    ->schema([
                        Forms\Components\Select::make('order_id')
                            ->relationship('order', 'number')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\TextInput::make('case_number')
                            ->required()
                            ->unique(ChargebackCase::class, 'case_number', ignoreRecord: true)
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\TextInput::make('payment_reference')
                            ->required()
                            ->helperText('Payment gateway reference (e.g., stripe_ch_xxx)'),

                        Forms\Components\Select::make('status')
                            ->options(ChargebackStatus::labels())
                            ->required()
                            ->color(fn ($state) => ChargebackStatus::from($state)->color())
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make('Chargeback Details')
                    ->schema([
                        Forms\Components\TextInput::make('reason_code')
                            ->required()
                            ->helperText('Issuer reason code (e.g., 4855)'),

                        Forms\Components\TextInput::make('reason_description')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->currencyMask(thousandsSeparator: ',', decimalSeparator: '.')
                            ->required(),

                        Forms\Components\TextInput::make('card_last_four')
                            ->maxLength(4)
                            ->placeholder('XXXX'),

                        Forms\Components\DatePicker::make('transaction_date')
                            ->native(false),

                        Forms\Components\DatePicker::make('chargeback_date')
                            ->native(false),

                        Forms\Components\DatePicker::make('due_date')
                            ->native(false)
                            ->helperText('Deadline for evidence submission'),
                    ])
                    ->columns(2),

                Section::make('Customer & Response')
                    ->schema([
                        Forms\Components\Textarea::make('customer_statement')
                            ->rows(3)
                            ->helperText('Customer\'s claim or statement'),

                        Forms\Components\Textarea::make('merchant_response')
                            ->rows(3)
                            ->helperText('Our response to the chargeback'),

                        Forms\Components\Textarea::make('resolution_notes')
                            ->rows(3)
                            ->helperText('Final resolution notes'),
                    ])
                    ->columnSpanFull(),

                Section::make('Resolution')
                    ->schema([
                        Forms\Components\Select::make('handled_by')
                            ->relationship('handledByUser', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\DateTimePicker::make('resolved_at')
                            ->native(false)
                            ->visible(fn ($record) => $record?->isResolved()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('case_number')
                    ->label('Case #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('order.number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => ChargebackStatus::from($state)->color())
                    ->formatStateUsing(fn ($state) => ChargebackStatus::from($state)->label()),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason_code')
                    ->label('Reason')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record?->isOverdue() ? 'danger' : null),

                Tables\Columns\TextColumn::make('evidence_count')
                    ->label('Evidence')
                    ->formatStateUsing(fn ($record) => $record?->getEvidenceCount() ?? 0)
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Opened')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ChargebackStatus::labels()),

                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue')
                    ->query(fn (Builder $query) => $query->whereRaw('DATE(due_date) < CURDATE() AND status NOT IN (?, ?, ?, ?)', [
                        ChargebackStatus::WON->value,
                        ChargebackStatus::LOST->value,
                        ChargebackStatus::SETTLED->value,
                        ChargebackStatus::WITHDRAWN->value,
                    ])),

                Tables\Filters\Filter::make('awaiting_evidence')
                    ->label('Awaiting Evidence')
                    ->query(fn (Builder $query) => $query->where('status', ChargebackStatus::AWAITING_EVIDENCE->value)),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('view_evidence')
                    ->icon('heroicon-o-document')
                    ->url(fn ($record) => ChargebackCaseResource::getUrl('evidence', ['record' => $record]))
                    ->openUrlInNewTab(),
                Action::make('generate_bundle')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn ($record) => static::generateEvidenceBundle($record))
                    ->visible(fn ($record) => $record->getEvidenceCount() > 0),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('opened_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChargebackCases::route('/'),
            'create' => Pages\CreateChargebackCase::route('/create'),
            'edit' => Pages\EditChargebackCase::route('/{record}/edit'),
            'view' => Pages\ViewChargebackCase::route('/{record}'),
            'evidence' => Pages\ManageChargebackEvidence::route('/{record}/evidence'),
        ];
    }

    private static function generateEvidenceBundle(ChargebackCase $record): void
    {
        $service = app(\App\Domain\Orders\Services\ChargebackEvidenceService::class);
        
        try {
            $bundle = $service->exportAsText($record);
            Notification::make()
                ->title('Evidence Bundle Generated')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error generating bundle')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
