<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Campaigns\Models\CampaignParticipation;
use App\Filament\Resources\CampaignParticipationResource\Pages;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CampaignParticipationResource extends BaseResource
{
    protected static ?string $model = CampaignParticipation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 21;

    protected static bool $staffReadOnly = true;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Participation')
                ->schema([
                    Forms\Components\TextInput::make('spot_number')
                        ->label('Spot number')
                        ->numeric()
                        ->disabled(),
                    Forms\Components\Select::make('state')
                        ->options([
                            'qualified' => 'Qualified',
                            'spot_reserved' => 'Spot Reserved',
                            'reward_issued' => 'Reward Issued',
                            'winner' => 'Winner',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('guaranteed_reward_type')
                        ->label('Guaranteed reward type')
                        ->disabled(),
                    Forms\Components\TextInput::make('guaranteed_reward_value')
                        ->label('Guaranteed reward value')
                        ->numeric()
                        ->disabled(),
                    Forms\Components\TextInput::make('reward_code')
                        ->label('Reward code')
                        ->disabled(),
                    Forms\Components\DateTimePicker::make('reward_issued_at')
                        ->label('Reward issued at')
                        ->disabled(),
                    Forms\Components\DateTimePicker::make('qualified_at')
                        ->label('Qualified at')
                        ->disabled(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('campaign.name')->label('Campaign')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.email')->label('Customer')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('spot_number')->label('Spot')->sortable(),
                Tables\Columns\TextColumn::make('state')->badge()->sortable(),
                Tables\Columns\TextColumn::make('reward_code')->label('Reward code')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('qualified_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('campaign_id')
                    ->label('Campaign')
                    ->relationship('campaign', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('state')
                    ->options([
                        'qualified' => 'Qualified',
                        'spot_reserved' => 'Spot Reserved',
                        'reward_issued' => 'Reward Issued',
                        'winner' => 'Winner',
                    ]),
                Tables\Filters\Filter::make('has_spot')
                    ->label('Has spot')
                    ->query(fn (Builder $query) => $query->whereNotNull('spot_number')),
            ])
            ->defaultSort('spot_number')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaignParticipations::route('/'),
            'edit' => Pages\EditCampaignParticipation::route('/{record}/edit'),
        ];
    }
}
