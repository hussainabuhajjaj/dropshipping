<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Affiliates\Models\AffiliateReferral;
use App\Filament\Resources\AffiliateReferralResource\Pages;
use App\Filament\Resources\BaseResource;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class AffiliateReferralResource extends BaseResource
{
    protected static ?string $model = AffiliateReferral::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Referrals';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAffiliateReferrals::route('/'),
        ];
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Affiliates';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
           Section::make('Referral Info')
                ->schema([
                    Forms\Components\TextInput::make('visitor_token')->disabled(),
                    Forms\Components\TextInput::make('affiliate.name')->label('Affiliate')->disabled(),
                    Forms\Components\TextInput::make('user.email')->label('Customer')->disabled(),
                    Forms\Components\DateTimePicker::make('expires_at')->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('affiliate.name')->label('Affiliate')->searchable(),
                Tables\Columns\TextColumn::make('visitor_token')->limit(32),
                Tables\Columns\TextColumn::make('user.email')->label('Customer')->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn (AffiliateReferral $record) => $record->user_id ? 'converted' : 'active')
                    ->colors([
                        'success' => 'converted',
                        'primary' => 'active',
                    ]),
                Tables\Columns\TextColumn::make('expires_at')->dateTime(),
                Tables\Columns\TextColumn::make('created_at')->label('Referred at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('converted')
                    ->label('Converted')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ])
                    ->query(fn ($query, $state) => $state === '1' ? $query->whereNotNull('user_id') : $query->whereNull('user_id')),
            ])
            ->recordActions([
               ViewAction::make(),
            ]);
    }
}
