<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use BackedEnum;
use App\Filament\Resources\BaseResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;

class CouponResource extends BaseResource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Basic Information')
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->unique('coupons', 'code', ignoreRecord: true)
                        ->placeholder('SUMMER2024'),
                    Forms\Components\TextInput::make('description')
                        ->maxLength(255)
                        ->placeholder('Summer discount for all products'),
                    Forms\Components\Select::make('type')
                        ->options([
                            'percent' => 'Percentage (%)',
                            'fixed' => 'Fixed Amount ($)',
                        ])
                        ->required()
                        ->reactive(),
                    Forms\Components\TextInput::make('amount')
                        ->numeric()
                        ->required()
                        ->step(0.01)
                        ->suffix(fn ($get) => $get('type') === 'percent' ? '%' : '$'),
                ])->columns(2),

            Section::make('Targeting')
                ->schema([
                    Forms\Components\Select::make('applicable_to')
                        ->label('Apply To')
                        ->options([
                            'all' => 'All Products',
                            'products' => 'Specific Products',
                            'categories' => 'Specific Categories',
                        ])
                        ->required()
                        ->reactive(),
                    
                    Forms\Components\MultiSelect::make('products')
                        ->relationship('products', 'name')
                        ->searchable()
                        ->preload()
                        ->visible(fn ($get) => $get('applicable_to') === 'products')
                        ->label('Select Products'),
                    
                    Forms\Components\MultiSelect::make('categories')
                        ->relationship('categories', 'name')
                        ->searchable()
                        ->preload()
                        ->visible(fn ($get) => $get('applicable_to') === 'categories')
                        ->label('Select Categories'),

                    Forms\Components\Toggle::make('exclude_on_sale')
                        ->label('Exclude Products Already on Sale')
                        ->default(false),
                ])->columns(2),

            Section::make('Constraints')
                ->schema([
                    Forms\Components\TextInput::make('min_order_total')
                        ->numeric()
                        ->step(0.01)
                        ->prefix('$')
                        ->label('Minimum Order Total'),
                    
                    Forms\Components\TextInput::make('max_uses')
                        ->numeric()
                        ->label('Maximum Uses (0 = unlimited)'),
                    
                    Forms\Components\Toggle::make('is_one_time_per_customer')
                        ->label('One Time Per Customer')
                        ->default(false),
                ])->columns(2),

            Section::make('Availability')
                ->schema([
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('Start Date')
                        ->native(false),
                    
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label('End Date')
                        ->native(false),
                    
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'percent',
                        'success' => 'fixed',
                    ]),
                Tables\Columns\TextColumn::make('amount')
                    ->sortable(),
                Tables\Columns\TextColumn::make('applicable_to')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('uses')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('max_uses')
                    ->numeric()
                    ->sortable()
                    ->toggleable()
                    ->label('Max'),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'percent' => 'Percentage',
                        'fixed' => 'Fixed',
                    ]),
                Tables\Filters\SelectFilter::make('applicable_to')
                    ->options([
                        'all' => 'All Products',
                        'products' => 'Specific Products',
                        'categories' => 'Specific Categories',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
               BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}


