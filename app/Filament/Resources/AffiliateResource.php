<?php

namespace App\Filament\Resources;

use App\Domain\Affiliates\Models\Affiliate;
use App\Filament\Resources\AffiliateResource\Pages;
use App\Filament\Resources\BaseResource;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class AffiliateResource extends BaseResource
{
    protected static ?string $model = Affiliate::class;

    protected static ?int $navigationSort = 1;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Affiliates';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAffiliates::route('/'),
            'create' => Pages\CreateAffiliate::route('/create'),
            'edit' => Pages\EditAffiliate::route('/{record}/edit'),
        ];
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Affiliate Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('referral_code')
                            ->label('Referral Code')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->maxLength(255)
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Leave blank to keep the current password when editing.')
                            ->dehydrateStateUsing(fn ($state) => $state ? (Hash::needsRehash($state) ? Hash::make($state) : $state) : null)
                            ->required(fn ($context) => $context instanceof Pages\CreateAffiliate),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'suspended' => 'Suspended',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('commission_rate')
                            ->label('Commission Rate')
                            ->numeric()
                            ->step(0.0001)
                            ->default(0.10),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('referral_code')
                    ->label('Referral Code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('commission_rate')
                    ->label('Commission Rate')
                    ->formatStateUsing(fn ($state) => $state * 100 . '%'),
                Tables\Columns\TextColumn::make('balance_available')
                    ->label('Available Balance')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_earned')
                    ->label('Total Earned')
                    ->money('USD')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
              BulkActionGroup::make([
                   BulkAction::make('approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['status' => 'approved'])),
                   BulkAction::make('suspend')
                        ->label('Suspend Selected')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each->update(['status' => 'suspended'])),
                ]),
            ]);
    }
}
