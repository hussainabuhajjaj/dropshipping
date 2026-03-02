<?php

namespace App\Filament\Resources;

use App\Domain\Affiliates\Models\AffiliateCommission;
use App\Domain\Affiliates\Services\AffiliateCommissionService;
use App\Filament\Resources\AffiliateCommissionResource\Pages;
use App\Filament\Resources\BaseResource;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AffiliateCommissionResource extends BaseResource
{
    protected static ?string $model = AffiliateCommission::class;

    protected static ?int $navigationSort = 2;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAffiliateCommissions::route('/'),
            'edit' => Pages\EditAffiliateCommission::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Affiliates';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
            Section::make('Commission Details')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'paid' => 'Paid',
                            ])
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_id')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'paid' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'paid' => 'Paid',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->requiresConfirmation()
                    ->action(fn (AffiliateCommission $record) => app(AffiliateCommissionService::class)->approveCommission($record))
                    ->color('success')
                    ->icon('heroicon-o-check'),
                Action::make('reject')
                    ->label('Reject')
                    ->requiresConfirmation()
                    ->action(fn (AffiliateCommission $record) => app(AffiliateCommissionService::class)->rejectCommission($record))
                    ->color('danger')
                    ->icon('heroicon-o-x-mark'),
               Action::make('mark_paid')
                    ->label('Mark as Paid')
                    ->requiresConfirmation()
                    ->action(fn (AffiliateCommission $record) => app(AffiliateCommissionService::class)->markCommissionPaid($record))
                    ->icon('heroicon-o-banknotes'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                  BulkAction::make('approve')
                        ->label('Approve selected')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each(fn (AffiliateCommission $record) => app(AffiliateCommissionService::class)->approveCommission($record))),
                    BulkAction::make('reject')
                        ->label('Reject selected')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each(fn (AffiliateCommission $record) => app(AffiliateCommissionService::class)->rejectCommission($record))),
                    BulkAction::make('mark_paid')
                        ->label('Mark paid')
                        ->icon('heroicon-o-banknotes')
                        ->action(fn ($records) => $records->each(fn (AffiliateCommission $record) => app(AffiliateCommissionService::class)->markCommissionPaid($record))),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
