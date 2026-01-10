<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromotionResource\Pages;
use App\Models\Promotion;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-bolt';
    protected static UnitEnum|string|null $navigationGroup = 'Marketing';
    protected static ?string $label = 'Promotion';
    protected static ?string $pluralLabel = 'Promotions';

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Promotion Details')->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\Textarea::make('description'),
                Forms\Components\Select::make('type')
                    ->options([
                        'flash_sale' => 'Flash Sale',
                        'auto_discount' => 'Automatic Discount',
                    ])->required(),
                Forms\Components\Select::make('value_type')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed' => 'Fixed Amount',
                        'free_shipping' => 'Free Shipping',
                    ])->required(),
                Forms\Components\TextInput::make('value')->numeric()->required(),
                Forms\Components\DateTimePicker::make('start_at'),
                Forms\Components\DateTimePicker::make('end_at'),
                Forms\Components\TextInput::make('priority')->numeric()->default(0),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\Select::make('stacking_rule')
                    ->options([
                        'combinable' => 'Combinable',
                        'exclusive' => 'Exclusive',
                    ])->default('combinable'),
            ]),
            Section::make('Targets')
                ->description('Define which products or categories this promotion applies to.')
                ->schema([
                    Forms\Components\Repeater::make('targets')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('target_type')
                                ->options([
                                    'product' => 'Product',
                                    'category' => 'Category',
                                ])
                                ->required()
                                ->live()
                                ->label('Target Type'),
                            Forms\Components\Select::make('target_id')
                                ->label('Target')
                                ->searchable()
                                ->getOptionLabelFromRecordUsing(function ($record, $component) {
                                    if ($component->getParentComponent()->getState()['target_type'] === 'product') {
                                        return optional(\App\Models\Product::find($record))->name;
                                    }
                                    if ($component->getParentComponent()->getState()['target_type'] === 'category') {
                                        return optional(\App\Models\Category::find($record))->name;
                                    }
                                    return $record;
                                })
                                ->options(function ($get) {
                                    if ($get('target_type') === 'product') {
                                        return \App\Models\Product::pluck('name', 'id');
                                    }
                                    if ($get('target_type') === 'category') {
                                        return \App\Models\Category::pluck('name', 'id');
                                    }
                                    return [];
                                })
                                ->required()
                                ->helperText('Select the product or category.'),
                        ])
                        ->label('Promotion Targets')
                        ->createItemButtonLabel('Add Target'),
                ]),
            Section::make('Conditions')
                ->description('Set rules for when this promotion is valid.')
                ->schema([
                    Forms\Components\Repeater::make('conditions')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('condition_type')
                                ->options([
                                    'min_cart_value' => 'Minimum Cart Value',
                                ])
                                ->required()
                                ->label('Condition Type'),
                            Forms\Components\TextInput::make('condition_value')
                                ->numeric()
                                ->label('Value')
                                ->helperText('For minimum cart value, enter the minimum subtotal required.'),
                        ])
                        ->label('Promotion Conditions')
                        ->createItemButtonLabel('Add Condition'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('value_type'),
                Tables\Columns\TextColumn::make('value'),
                Tables\Columns\TextColumn::make('start_at')->dateTime(),
                Tables\Columns\TextColumn::make('end_at')->dateTime(),
                ToggleColumn::make('is_active'),
            ])
            ->filters([
                // Add filters if needed
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotions::route('/'),
            'create' => Pages\CreatePromotion::route('/create'),
            'edit' => Pages\EditPromotion::route('/{record}/edit'),
        ];
    }
}
