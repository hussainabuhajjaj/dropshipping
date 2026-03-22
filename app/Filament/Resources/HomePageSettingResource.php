<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\HomePageSettingResource\Pages;
use App\Models\HomePageSetting;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction as ActionsEditAction;
use Filament\Forms;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;

class HomePageSettingResource extends BaseResource
{
    protected static ?string $model = HomePageSetting::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-home-modern';

    protected static string|\UnitEnum|null $navigationGroup = 'Storefront';
    protected static bool $adminOnly = true;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Locale')
                ->schema([
                    Forms\Components\Select::make('locale')
                        ->options([
                            'en' => 'English',
                            'fr' => 'French',
                        ])
                        ->native(false)
                        ->nullable()
                        ->placeholder('Default')
                        ->helperText('Leave empty to use this record as the default for all locales.'),
                ]),
            Section::make('Top strip')
                ->schema([
                    Forms\Components\Repeater::make('top_strip')
                        ->schema([
                            Forms\Components\TextInput::make('icon')->maxLength(4)->default('⚡'),
                            Forms\Components\TextInput::make('title')->required(),
                            Forms\Components\TextInput::make('subtitle')->required(),
                        ])
                        ->columns(3)
                        ->minItems(1)
                        ->reorderable(),
                ]),
            Section::make('Hero slides')
                ->schema([
                    Forms\Components\Repeater::make('hero_slides')
                        ->schema([
                            Forms\Components\TextInput::make('kicker')->required(),
                            Forms\Components\TextInput::make('title')->required(),
                            Forms\Components\Textarea::make('subtitle')->rows(2)->required(),
                            Forms\Components\FileUpload::make('image')
                                ->label('Image')
                                ->disk('public')
                                ->directory('home')
                                ->image()
                                ->imageEditor()
                                ->required(),
                            Forms\Components\TextInput::make('primary_label')->default('Shop now'),
                            Forms\Components\TextInput::make('primary_href')->default('/products'),
                            Forms\Components\TextInput::make('secondary_label')->default('Track order'),
                            Forms\Components\TextInput::make('secondary_href')->default('/orders/track'),
                            Forms\Components\TagsInput::make('meta')->separator(','),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->reorderable(),
                ]),
            Section::make('Hero rail cards')
                ->schema([
                    Forms\Components\Repeater::make('rail_cards')
                        ->schema([
                            Forms\Components\TextInput::make('kicker')->required(),
                            Forms\Components\TextInput::make('title')->required(),
                            Forms\Components\Textarea::make('subtitle')->rows(2)->required(),
                            Forms\Components\TextInput::make('cta')->label('CTA label')->required(),
                            Forms\Components\TextInput::make('href')->label('CTA link')->required(),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->reorderable(),
                ]),
            Section::make('Category highlights')
                ->schema([
                    Forms\Components\Repeater::make('category_highlights')
                        ->schema([
                            Forms\Components\Select::make('category_id')
                                ->label('Category')
                                ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(fn ($state, callable $set) => $set('category_name', optional(Category::find($state))->name)),
                            Forms\Components\TextInput::make('category_name')
                                ->label('Category Name')
                                ->disabled()
                                ->default(fn (callable $get) => optional(Category::find($get('category_id')))->name),
                            Forms\Components\TextInput::make('title')
                                ->label('Custom Title')
                                ->placeholder('Leave empty to use category name')
                                ->helperText('Override the category name with a custom title'),
                            Forms\Components\Textarea::make('description')
                                ->label('Description')
                                ->rows(2)
                                ->placeholder('Brief description of this category highlight'),
                            Forms\Components\FileUpload::make('image')
                                ->label('Custom Image')
                                ->disk('public')
                                ->directory('home/categories')
                                ->image()
                                ->placeholder('Leave empty to use category hero image')
                                ->helperText('Override the category hero image with a custom one'),
                            Forms\Components\TextInput::make('cta_label')
                                ->label('CTA Button Text')
                                ->default('Shop Now')
                                ->placeholder('Button text'),
                            Forms\Components\TextInput::make('cta_link')
                                ->label('CTA Button Link')
                                ->placeholder('/products?category=slug')
                                ->helperText('Leave empty to auto-generate from category slug'),
                            Forms\Components\ColorPicker::make('accent_color')
                                ->label('Accent Color')
                                ->placeholder('Theme color for this highlight'),
                            Forms\Components\Toggle::make('featured')
                                ->label('Featured')
                                ->default(false)
                                ->helperText('Mark this as a featured category highlight'),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->maxItems(6)
                        ->reorderable(),
                ])
                ->collapsible()
                ->collapsed(),
            Section::make('Banner strip')
                ->schema([
                    Forms\Components\TextInput::make('banner_strip.kicker')->required(),
                    Forms\Components\TextInput::make('banner_strip.title')->required(),
                    Forms\Components\TextInput::make('banner_strip.cta')->label('CTA label')->required(),
                    Forms\Components\TextInput::make('banner_strip.href')->label('CTA link')->required(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('locale')
                    ->formatStateUsing(fn (?string $state): string => $state ?: 'Default')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime(),
            ])
            ->recordActions([
                ActionsEditAction::make(),
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url('/')
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHomePageSettings::route('/'),
            'create' => Pages\CreateHomePageSetting::route('/create'),
            'edit' => Pages\EditHomePageSetting::route('/{record}/edit'),
        ];
    }
}

