<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Marketing\Models\QrCampaign;
use App\Filament\Resources\QrCampaignResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class QrCampaignResource extends BaseResource
{
    protected static ?string $model = QrCampaign::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campaign')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(3),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('URL path: /r/{slug}'),
                        Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Reward')
                    ->schema([
                        Select::make('reward_type')
                            ->required()
                            ->options([
                                'product' => 'Free Product',
                                'money' => 'Money / Credit',
                                'points' => 'Points',
                            ])
                            ->reactive()
                            ->afterStateUpdated(fn ($set) => $set('product_id', null)),
                        TextInput::make('reward_value')
                            ->numeric()
                            ->visible(fn ($get) => in_array($get('reward_type'), ['money', 'points']))
                            ->required(fn ($get) => in_array($get('reward_type'), ['money', 'points']))
                            ->helperText('Enter amount (e.g. 5000 for FCFA, 100 for points)'),
                        Select::make('product_id')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->visible(fn ($get) => $get('reward_type') === 'product')
                            ->required(fn ($get) => $get('reward_type') === 'product'),
                    ])
                    ->columns(2),

                Section::make('Limits & Schedule')
                    ->schema([
                        TextInput::make('max_claims')
                            ->numeric()
                            ->placeholder('Unlimited'),
                        DateTimePicker::make('starts_at'),
                        DateTimePicker::make('expires_at'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Slug copied'),
                Tables\Columns\TextColumn::make('reward_type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'product' => 'success',
                        'money' => 'warning',
                        'points' => 'info',
                    }),
                Tables\Columns\TextColumn::make('reward_type')
                    ->label('Reward')
                    ->formatStateUsing(fn ($state, $record) => $record->rewardLabel()),
                Tables\Columns\TextColumn::make('claim_count')
                    ->sortable()
                    ->label('Claims'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('reward_type')
                    ->options([
                        'product' => 'Product',
                        'money' => 'Money',
                        'points' => 'Points',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                EditAction::make(),
                Action::make('qr_code')
                    ->label('QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn (QrCampaign $record) => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode(url('/r/' . $record->slug)))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQrCampaigns::route('/'),
            'create' => Pages\CreateQrCampaign::route('/create'),
            'edit' => Pages\EditQrCampaign::route('/{record}/edit'),
        ];
    }
}
