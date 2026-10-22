<?php

namespace App\Filament\Resources;

use App\Domain\Affiliates\Models\AffiliateWithdrawal;
use App\Domain\Affiliates\Services\AffiliateWithdrawalService;
use App\Filament\Resources\AffiliateWithdrawalResource\Pages;
use App\Filament\Resources\BaseResource;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AffiliateWithdrawalResource extends BaseResource
{
    protected static ?string $model = AffiliateWithdrawal::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?int $navigationSort = 3;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAffiliateWithdrawals::route('/'),
            'edit' => Pages\EditAffiliateWithdrawal::route('/{record}/edit'),
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
                Section::make('Withdrawal Details')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->required()
                            ->step(0.01),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'processed' => 'Processed',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processed' => 'success',
                        'rejected' => 'danger',
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
                        'processed' => 'Processed',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                Action::make('process')
                    ->label('Process')
                    ->requiresConfirmation()
                    ->action(fn (AffiliateWithdrawal $record) => app(AffiliateWithdrawalService::class)->processWithdrawal($record))
                    ->icon('heroicon-o-check')
                    ->color('success'),
                Action::make('reject')
                    ->label('Reject')
                    ->requiresConfirmation()
                    ->action(fn (AffiliateWithdrawal $record) => app(AffiliateWithdrawalService::class)->rejectWithdrawal($record))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('process')
                        ->label('Process selected')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each(fn (AffiliateWithdrawal $record) => app(AffiliateWithdrawalService::class)->processWithdrawal($record))),
                    BulkAction::make('reject')
                        ->label('Reject selected')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each(fn (AffiliateWithdrawal $record) => app(AffiliateWithdrawalService::class)->rejectWithdrawal($record))),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
