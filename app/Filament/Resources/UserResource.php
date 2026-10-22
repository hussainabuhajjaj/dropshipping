<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 10;
    protected static bool $adminOnly = true;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('User')
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(120),
                    Forms\Components\TextInput::make('email')->required()->email()->maxLength(255),
                    Forms\Components\Select::make('role')
                        ->options([
                            'admin' => 'Admin',
                            'staff' => 'Staff',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn ($record) => $record === null),
                    Forms\Components\Toggle::make('two_factor_enabled')
                        ->label('2FA enabled')
                        ->helperText('Enable after configuring a 2FA secret for this user.'),
                    Forms\Components\TextInput::make('two_factor_secret')
                        ->label('2FA secret')
                        ->password()
                        ->helperText('Store the TOTP secret for app-based authentication.')
                        ->dehydrateStateUsing(fn ($state) => $state ?: null),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('role')->badge()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}



