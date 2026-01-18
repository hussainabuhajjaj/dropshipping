<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromotionResource\Pages;
use App\Models\Promotion;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
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
                Forms\Components\Select::make('promotion_intent')
                    ->label('Intent')
                    ->options([
                        'shipping_support' => 'Shipping support',
                        'cart_growth' => 'Cart growth',
                        'urgency' => 'Urgency',
                        'acquisition' => 'Acquisition',
                        'other' => 'Other',
                    ])
                    ->default('other')
                    ->required(),
                Forms\Components\Select::make('display_placements')
                    ->label('Display placements')
                    ->multiple()
                    ->options([
                        'home' => 'Homepage',
                        'category' => 'Category page',
                        'product' => 'Product page',
                        'cart' => 'Cart',
                        'checkout' => 'Checkout',
                    ])
                    ->helperText('Leave empty to allow display on all placements.'),
                Placeholder::make('duration_warning')
                    ->label('Timing warning')
                    ->content(function (callable $get): string {
                        $start = $get('start_at');
                        $end = $get('end_at');

                        if (! $start || ! $end) {
                            return 'Set both start and end times to validate duration.';
                        }

                        try {
                            $startAt = Carbon::parse($start);
                            $endAt = Carbon::parse($end);
                        } catch (\Throwable) {
                            return 'Unable to read start/end times.';
                        }

                        if ($endAt->lte($startAt)) {
                            return 'End time must be after start time.';
                        }

                        $diffSeconds = $endAt->diffInSeconds($startAt);
                        if ($diffSeconds < 300) {
                            return 'Promotion duration is under 5 minutes. Short windows are easy to miss and may not display as expected.';
                        }

                        return 'Duration looks OK.';
                    })
                    ->visible(function (callable $get): bool {
                        $start = $get('start_at');
                        $end = $get('end_at');

                        if (! $start || ! $end) {
                            return true;
                        }

                        try {
                            $startAt = Carbon::parse($start);
                            $endAt = Carbon::parse($end);
                        } catch (\Throwable) {
                            return true;
                        }

                        if ($endAt->lte($startAt)) {
                            return true;
                        }

                        return $endAt->diffInSeconds($startAt) < 300;
                    })
                    ->extraAttributes(['class' => 'text-sm text-amber-600']),
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
                                    'max_discount' => 'Maximum Discount',
                                    'first_order_only' => 'First Order Only',
                                ])
                                ->required()
                                ->label('Condition Type'),
                            Forms\Components\TextInput::make('condition_value')
                                ->numeric()
                                ->label('Value')
                                ->helperText('For first-order-only, leave blank. For max discount, enter the cap amount.'),
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
