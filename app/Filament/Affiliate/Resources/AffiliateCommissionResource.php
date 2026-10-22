<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources;

use App\Domain\Affiliates\Models\AffiliateCommission;
use App\Filament\Affiliate\Resources\AffiliateCommissionResource\Pages;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AffiliateCommissionResource extends Resource
{
    protected static ?string $model = AffiliateCommission::class;

    protected static string | UnitEnum | null $navigationGroup = 'Affiliate';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'My Commissions';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\Placeholder::make('info')
                ->content('Affiliates are not allowed to manually edit commission records.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_rate')
                    ->label('Rate')
                    ->formatStateUsing(fn (float $state): string => number_format($state * 100, 2) . '%'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'primary' => 'paid',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
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
            'index' => Pages\ListMyCommissions::route('/'),
        ];
    }
}
