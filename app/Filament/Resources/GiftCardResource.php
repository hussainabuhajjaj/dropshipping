<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\GiftCardResource\Pages;
use App\Models\GiftCard;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class GiftCardResource extends BaseResource
{
    protected static ?string $model = GiftCard::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';
    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Gift card')
                ->schema([
                    Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true),
                    Forms\Components\Select::make('status')->options([
                        'active' => 'Active',
                        'redeemed' => 'Redeemed',
                        'expired' => 'Expired',
                    ])->required(),
                    Forms\Components\TextInput::make('balance')->numeric()->required(),
                    Forms\Components\TextInput::make('currency')->default('USD')->maxLength(3),
                    Forms\Components\TextInput::make('customer_id')->label('Customer ID')->numeric(),
                    Forms\Components\DateTimePicker::make('expires_at'),
                    Forms\Components\DateTimePicker::make('redeemed_at'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('balance')->sortable(),
                Tables\Columns\TextColumn::make('currency')->sortable(),
                Tables\Columns\TextColumn::make('customer.email')->label('Customer')->sortable()->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('redeemed_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'redeemed' => 'Redeemed',
                    'expired' => 'Expired',
                ]),
            ])
            ->recordActions([
               EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGiftCards::route('/'),
            'create' => Pages\CreateGiftCard::route('/create'),
            'edit' => Pages\EditGiftCard::route('/{record}/edit'),
        ];
    }
}



