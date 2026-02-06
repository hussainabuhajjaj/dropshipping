<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\StorefrontCollectionResource\Pages;
use App\Models\Category;
use App\Models\Product;
use App\Models\StorefrontCollection;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;

use Filament\Tables\Table;
use Illuminate\Support\Str;

class StorefrontCollectionResource extends BaseResource
{
    protected static ?string $model = StorefrontCollection::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string|\UnitEnum|null $navigationGroup = 'Storefront';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->schema([
            Section::make('Basics')
                ->description('General collection metadata and visibility options.')
                ->columns(2)
                ->columnSpan(6)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(160),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(160),
                    Forms\Components\Select::make('type')
                        ->options([
                            'collection' => 'Collection',
                            'guide' => 'Buying Guide',
                            'seasonal' => 'Seasonal Drop',
                            'drop' => 'Drop',
                        ])
                        ->required(),
                    Forms\Components\Textarea::make('description')
                        ->rows(3),
                    Forms\Components\TextInput::make('display_order')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                ]),

            Section::make('Hero')
                ->description('Configure the hero section that appears above the collection content.')
                ->columns(2)
                ->columnSpan(6)
                ->schema([
                    Forms\Components\TextInput::make('hero_kicker')
                        ->maxLength(120),
                    Forms\Components\Textarea::make('hero_subtitle')
                        ->rows(2),
                    Forms\Components\FileUpload::make('hero_image')
                        ->label('Hero image')
                        ->disk('public')
                        ->directory('collections')
                        ->image()
                        ->imageEditor(),
                    Forms\Components\TextInput::make('hero_cta_label')
                        ->label('CTA label'),
                    Forms\Components\TextInput::make('hero_cta_url')
                        ->label('CTA URL')
                        ->placeholder('/collections/global-home-lab')
                        ->dehydrateStateUsing(fn (?string $state): ?string => self::normalizeRelativeUrl($state)),
                ]),

            Section::make('Content')
                ->description('Rich content and SEO metadata displayed on the landing page.')
                ->columnSpan('full')
                ->schema([
                    Forms\Components\RichEditor::make('content')
                        ->label('Landing page content')
                        ->columnSpan('full'),
                    Grid::make()
                        ->columns(2)
                        ->schema([
                            Forms\Components\TextInput::make('seo_title')
                                ->label('SEO title')
                                ->maxLength(180),
                            Forms\Components\Textarea::make('seo_description')
                                ->label('SEO description')
                                ->rows(2),
                        ]),
                ]),

            Section::make('Schedule & locale')
                ->description('Control when the collection appears and which locales can see it.')
                ->columns(2)
                ->columnSpan(6)
                ->schema([
                    Forms\Components\DateTimePicker::make('starts_at')->native(false),
                    Forms\Components\DateTimePicker::make('ends_at')->native(false),
                    Forms\Components\TextInput::make('timezone')
                        ->placeholder('Africa/Abidjan'),
                    Forms\Components\TagsInput::make('locale_visibility')
                        ->placeholder('en, fr'),
                ]),

            Section::make('Locale overrides')
                ->description('Optional translations and schedule overrides per locale.')
                ->columnSpan('6')
                ->schema([
                    Forms\Components\Repeater::make('locale_overrides')
                        ->columns(2)
                        ->schema([
                            Forms\Components\Select::make('locale')
                                ->options(static::localeOptions())
                                ->required(),
                            Forms\Components\TextInput::make('title')
                                ->maxLength(160),
                            Forms\Components\Textarea::make('description')
                                ->rows(2),
                            Forms\Components\TextInput::make('hero_kicker')
                                ->maxLength(120),
                            Forms\Components\Textarea::make('hero_subtitle')
                                ->rows(2),
                            Forms\Components\TextInput::make('hero_cta_label')
                                ->label('CTA label')
                                ->maxLength(80),
                            Forms\Components\TextInput::make('hero_cta_url')
                                ->label('CTA URL')
                                ->placeholder('/collections/global-home-lab')
                                ->dehydrateStateUsing(fn (?string $state): ?string => self::normalizeRelativeUrl($state)),
                            Forms\Components\RichEditor::make('content')
                                ->label('Landing page content')
                                ->columnSpan('full'),
                            Forms\Components\TextInput::make('seo_title')
                                ->label('SEO title')
                                ->maxLength(180),
                            Forms\Components\Textarea::make('seo_description')
                                ->label('SEO description')
                                ->rows(2),
                            Forms\Components\DateTimePicker::make('starts_at')->native(false),
                            Forms\Components\DateTimePicker::make('ends_at')->native(false),
                            Forms\Components\TextInput::make('timezone')
                                ->placeholder('Africa/Abidjan'),
                        ])
                        ->collapsible(),
                ]),

            Section::make('Product selection')
                ->description('Choose how products are pulled into this collection.')
                ->columnSpan('full')
                ->schema([
                    Grid::make()
                        ->columns(3)
                        ->schema([
                            Forms\Components\Select::make('selection_mode')
                                ->options([
                                    'rules' => 'Rule-based',
                                    'manual' => 'Manual',
                                    'hybrid' => 'Hybrid (Manual + Rules)',
                                ])
                                ->default('rules')
                                ->live()
                                ->required(),
                            Forms\Components\TextInput::make('product_limit')
                                ->numeric()
                                ->minValue(1)
                                ->placeholder('Leave blank for unlimited'),
                            Forms\Components\Select::make('sort_by')
                                ->options([
                                    'newest' => 'Newest',
                                    'price_asc' => 'Price: Low to High',
                                    'price_desc' => 'Price: High to Low',
                                    'rating' => 'Top rated',
                                    'popularity' => 'Most reviewed',
                                    'featured' => 'Featured first',
                                    'random' => 'Random',
                                ])
                                ->placeholder('Default sorting'),
                        ]),

                    Section::make('Rule-based filters')
                        ->columnSpan('full')
                        ->description('Filter by category, price, stock, and rating when rules are enabled.')
                        ->columns(2)
                        ->schema([
                            Forms\Components\Select::make('category_ids')
                                ->multiple()
                                ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id'))
                                ->searchable(),
                            Forms\Components\TextInput::make('min_price')
                                ->numeric()
                                ->label('Min price'),
                            Forms\Components\TextInput::make('max_price')
                                ->numeric()
                                ->label('Max price'),
                            Forms\Components\Toggle::make('in_stock')
                                ->label('In stock only')
                                ->default(true),
                            Forms\Components\Toggle::make('is_featured')
                                ->label('Featured only'),
                            Forms\Components\Select::make('min_rating')
                                ->options([
                                    5 => '5 stars',
                                    4 => '4 stars & up',
                                    3 => '3 stars & up',
                                ]),
                            Forms\Components\TextInput::make('query')
                                ->label('Search keyword'),
                            Forms\Components\Select::make('exclude_product_ids')
                                ->label('Exclude products')
                                ->multiple()
                                ->options(fn () => Product::query()->orderBy('name')->limit(200)->pluck('name', 'id'))
                                ->searchable(),
                        ])
                        ->visible(fn (Get $get) => in_array($get('selection_mode'), ['rules', 'hybrid'], true)),

                    Section::make('Manual products')
                        ->columnSpan('full')
                        ->description('Manually order featured products when manual mode is selected.')
                        ->schema([
                            Forms\Components\Repeater::make('manual_products')
                                ->schema([
                                    Forms\Components\Select::make('product_id')
                                        ->label('Product')
                                        ->options(fn () => Product::query()->orderBy('name')->limit(200)->pluck('name', 'id'))
                                        ->searchable()
                                        ->required(),
                                    Forms\Components\TextInput::make('position')
                                        ->numeric()
                                        ->default(0),
                                ])
                                ->columns(2)
                                ->reorderable()
                                ->visible(fn (Get $get) => in_array($get('selection_mode'), ['manual', 'hybrid'], true)),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('display_order')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStorefrontCollections::route('/'),
            'create' => Pages\CreateStorefrontCollection::route('/create'),
            'edit' => Pages\EditStorefrontCollection::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function localeOptions(): array
    {
        return [
            'en' => 'English',
            'fr' => 'Français',
        ];
    }

    private static function normalizeRelativeUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        if (Str::startsWith($trimmed, ['http://', 'https://'])) {
            $relativePath = parse_url($trimmed, PHP_URL_PATH) ?? '';
            $query = parse_url($trimmed, PHP_URL_QUERY);
            $trimmed = $relativePath . ($query ? ('?' . $query) : '');
        }

        return '/' . ltrim($trimmed, '/');
    }
}
