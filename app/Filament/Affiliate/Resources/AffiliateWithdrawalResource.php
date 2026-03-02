<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources;

use App\Domain\Affiliates\Models\AffiliateWithdrawal;
use App\Filament\Affiliate\Resources\AffiliateWithdrawalResource\Pages;
use BackedEnum;
use Filament\Facades\Filament;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AffiliateWithdrawalResource extends Resource
{
    protected static ?string $model = AffiliateWithdrawal::class;

    protected static string | UnitEnum | null $navigationGroup = 'Affiliate';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Withdrawals';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $form): Schema
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'processed',
                        'danger' => 'rejected',
                    ]),
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $affiliate = Filament::auth()->user();

        return $query->where('affiliate_id', $affiliate->id ?? null);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyWithdrawals::route('/'),
        ];
    }
}
