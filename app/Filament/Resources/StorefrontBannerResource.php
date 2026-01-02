<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Models\StorefrontBanner;
use BackedEnum;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Schemas\Components\Utilities\Get as SchemaGet;
use App\Filament\Resources\StorefrontBannerResource\Pages;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;

class StorefrontBannerResource extends BaseResource
{
    protected static ?string $model = StorefrontBanner::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';
    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 25;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Banner Details')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->placeholder('Summer Sale 2024'),
                    Forms\Components\TextInput::make('description')
                        ->placeholder('Get up to 50% off on selected items'),
                    Forms\Components\Select::make('type')
                        ->options([
                            'promotion' => 'Promotion',
                            'event' => 'Event',
                            'seasonal' => 'Seasonal',
                            'flash_sale' => 'Flash Sale',
                        ])
                        ->required(),
                ])->columns(2),

            Section::make('Display Settings')
                ->schema([
                    Forms\Components\Select::make('display_type')
                        ->options([
                            'hero' => 'Hero Banner (Full Width)',
                            'sidebar' => 'Sidebar',
                            'carousel' => 'Carousel',
                            'strip' => 'Strip Banner',
                        ])
                        ->required(),
                    Forms\Components\FileUpload::make('image_path')
                        ->label('Banner Image')
                        ->image()
                        ->directory('banners')
                        ->disk('public')
                        ->maxSize(5120)
                        ->imageEditor()
                        ->imageEditorAspectRatios([
                            null,
                            '16:9',
                            '4:3',
                            '21:9',
                            '1:1',
                        ])
                        ->live()
                        ->helperText('Upload a banner image (max 5MB). Will update preview in real-time.'),
                ])->columns(2),

            Section::make('Targeting')
                ->schema([
                    Forms\Components\Select::make('target_type')
                        ->options([
                            'none' => 'None (Show Everywhere)',
                            'product' => 'Specific Product',
                            'category' => 'Specific Category',
                            'url' => 'Custom URL',
                        ])
                        ->live(),
                    
                    Forms\Components\Select::make('product_id')
                        ->relationship('product', 'name')
                        ->searchable()
                        ->preload()
                        ->visible(fn ($get) => $get('target_type') === 'product'),
                    
                    Forms\Components\Select::make('category_id')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->visible(fn ($get) => $get('target_type') === 'category'),
                    
                    Forms\Components\TextInput::make('external_url')
                        ->url()
                        ->visible(fn ($get) => $get('target_type') === 'url'),
                ])->columns(2),

            Section::make('Styling')
                ->schema([
                    Forms\Components\ColorPicker::make('background_color')
                        ->default('#ffffff'),
                    Forms\Components\ColorPicker::make('text_color')
                        ->default('#000000'),
                    Forms\Components\TextInput::make('badge_text')
                        ->placeholder('Hot Deal'),
                    Forms\Components\Select::make('badge_color')
                        ->options([
                            'primary' => 'Primary',
                            'danger' => 'Danger',
                            'warning' => 'Warning',
                            'success' => 'Success',
                        ]),
                ])->columns(2),

            Section::make('Call to Action')
                ->schema([
                    Forms\Components\TextInput::make('cta_text')
                        ->placeholder('Shop Now')
                        ->label('Button Text'),
                    Forms\Components\TextInput::make('cta_url')
                        ->url()
                        ->placeholder('https://example.com/summer-sale')
                        ->label('Button URL'),
                ])->columns(2),

            Section::make('Live Preview')
                ->schema([
                    Forms\Components\ViewField::make('preview')
                        ->view('filament.storefront-banners.preview')
                        ->live()
                        ->viewData(fn (SchemaGet $get) => [
                            'data' => [
                                'title' => $get('title'),
                                'description' => $get('description'),
                                'badge_text' => $get('badge_text'),
                                'badge_color' => $get('badge_color'),
                                'cta_text' => $get('cta_text'),
                                'cta_url' => $get('cta_url'),
                                'background_color' => $get('background_color') ?: '#ffffff',
                                'text_color' => $get('text_color') ?: '#000000',
                                'image_path' => $get('image_path'),
                                'display_type' => $get('display_type') ?: 'hero',
                            ],
                        ])
                        ->columnSpan('full'),
                ])->collapsible(),

            Section::make('Availability')
                ->schema([
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->native(false),
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->native(false),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                    Forms\Components\TextInput::make('display_order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower numbers appear first'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('display_type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('target_type')
                    ->badge()
                    ->label('Target')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
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
                        'promotion' => 'Promotion',
                        'event' => 'Event',
                        'seasonal' => 'Seasonal',
                        'flash_sale' => 'Flash Sale',
                    ]),
                Tables\Filters\SelectFilter::make('display_type')
                    ->options([
                        'hero' => 'Hero Banner',
                        'sidebar' => 'Sidebar',
                        'carousel' => 'Carousel',
                        'strip' => 'Strip',
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
            'index' => Pages\ListStorefrontBanners::route('/'),
            'create' => Pages\CreateStorefrontBanner::route('/create'),
            'edit' => Pages\EditStorefrontBanner::route('/{record}/edit'),
        ];
    }
}
