<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Campaigns\Models\CampaignWinner;
use App\Filament\Resources\CampaignWinnerResource\Pages;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CampaignWinnerResource extends BaseResource
{
    protected static ?string $model = CampaignWinner::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';
    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 22;

    protected static bool $staffReadOnly = true;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Winner')
                ->schema([
                    Forms\Components\Select::make('prize_type')
                        ->options([
                            'grand' => 'Grand Prize',
                            'runner_up' => 'Runner-up',
                            'guaranteed' => 'Guaranteed Reward',
                        ])
                        ->disabled(),
                    Forms\Components\TextInput::make('prize_label')
                        ->label('Prize label')
                        ->disabled(),
                    Forms\Components\TextInput::make('reward_code')
                        ->label('Reward code')
                        ->disabled(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'delivered' => 'Delivered',
                            'fulfilled' => 'Fulfilled',
                            'expired' => 'Expired',
                        ])
                        ->required(),
                    Forms\Components\DateTimePicker::make('announced_at')
                        ->label('Announced at')
                        ->disabled(),
                    Forms\Components\DateTimePicker::make('delivered_at')
                        ->label('Delivered at')
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
                Tables\Columns\TextColumn::make('prize_type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('prize_label')->label('Prize')->toggleable(),
                Tables\Columns\TextColumn::make('reward_code')->label('Reward code')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('announced_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('delivered_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('campaign_id')
                    ->label('Campaign')
                    ->relationship('campaign', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('prize_type')
                    ->options([
                        'grand' => 'Grand Prize',
                        'runner_up' => 'Runner-up',
                        'guaranteed' => 'Guaranteed Reward',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'delivered' => 'Delivered',
                        'fulfilled' => 'Fulfilled',
                        'expired' => 'Expired',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(function (CampaignWinner $record) {
                        if ($record->wasChanged('status') && $record->status !== 'pending') {
                            $record->markDelivered($record->status);
                        }
                    }),
                Action::make('mark_delivered')
                    ->label('Mark delivered')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CampaignWinner $record) => $record->status === 'pending')
                    ->action(function (CampaignWinner $record) {
                        $record->markDelivered('delivered');
                        Notification::make()->title('Winner marked as delivered')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaignWinners::route('/'),
            'edit' => Pages\EditCampaignWinner::route('/{record}/edit'),
        ];
    }
}
