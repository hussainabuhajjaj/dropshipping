<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\StorefrontCampaignResource\Pages;
use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\StorefrontBanner;
use App\Models\StorefrontCampaign;
use App\Models\StorefrontCollection;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class StorefrontCampaignResource extends BaseResource
{
    protected static ?string $model = StorefrontCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';
    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Basics')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(160),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(160),
                    Forms\Components\Select::make('type')
                        ->options([
                            'seasonal' => 'Seasonal',
                            'drop' => 'Drop',
                            'event' => 'Event',
                        ])
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'pending_approval' => 'Pending Approval',
                            'approved' => 'Approved',
                            'scheduled' => 'Scheduled',
                            'active' => 'Active',
                            'rejected' => 'Rejected',
                            'ended' => 'Ended',
                        ])
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                    Forms\Components\TextInput::make('priority')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Select::make('stacking_mode')
                        ->options([
                            'stackable' => 'Stackable',
                            'exclusive' => 'Exclusive',
                        ])
                        ->default('stackable')
                        ->required()
                        ->live(),
                    Forms\Components\TextInput::make('exclusive_group')
                        ->placeholder('e.g. seasonal-major')
                        ->visible(fn (Get $get) => $get('stacking_mode') === 'exclusive'),
                ])
                ->columns(2),

            Section::make('Hero & content')
                ->schema([
                    Forms\Components\TextInput::make('hero_kicker')
                        ->maxLength(120),
                    Forms\Components\Textarea::make('hero_subtitle')
                        ->rows(2),
                    Forms\Components\FileUpload::make('hero_image')
                        ->label('Hero Image')
                        ->disk('public')
                        ->directory('campaigns')
                        ->image()
                        ->imageEditor(),
                    Forms\Components\RichEditor::make('content'),
                ])
                ->columns(2),

            Section::make('Theme & placements')
                ->schema([
                    Fieldset::make('theme')
                        ->statePath('theme')
                        ->schema([
                            Forms\Components\ColorPicker::make('primary')
                                ->default('#f59e0b'),
                            Forms\Components\ColorPicker::make('secondary')
                                ->default('#2563eb'),
                            Forms\Components\ColorPicker::make('accent')
                                ->default('#29ab87'),
                            Forms\Components\Select::make('image_mode')
                                ->label('Hero image mode')
                                ->options([
                                    'cover' => 'Full image + overlay',
                                    'split' => 'Split layout',
                                    'image_only' => 'Image only',
                                ])
                                ->default('cover'),
                        ])
                        ->columns(3),
                    Forms\Components\CheckboxList::make('placements')
                        ->options([
                            'home_hero' => 'Home hero',
                            'home_carousel' => 'Home carousel',
                            'home_strip' => 'Home strip',
                            'home_popup' => 'Popup modal',
                            'promotions_page' => 'Promotions page',
                            'collections_index' => 'Collections index',
                        ])
                        ->columns(2),
                ])
                ->columns(1),

            Section::make('Schedule & locale')
                ->schema([
                    Forms\Components\DateTimePicker::make('starts_at')->native(false),
                    Forms\Components\DateTimePicker::make('ends_at')->native(false),
                    Forms\Components\TextInput::make('timezone')
                        ->placeholder('Africa/Abidjan'),
                    Forms\Components\TagsInput::make('locale_visibility')
                        ->placeholder('en, fr'),
                    Forms\Components\Repeater::make('locale_overrides')
                        ->schema([
                            Forms\Components\Select::make('locale')
                                ->options(static::localeOptions())
                                ->required(),
                            Forms\Components\TextInput::make('name')
                                ->maxLength(160),
                            Forms\Components\TextInput::make('hero_kicker')
                                ->maxLength(120),
                            Forms\Components\Textarea::make('hero_subtitle')
                                ->rows(2),
                            Forms\Components\RichEditor::make('content')
                                ->label('Content')
                                ->columnSpan('full'),
                            Forms\Components\DateTimePicker::make('starts_at')->native(false),
                            Forms\Components\DateTimePicker::make('ends_at')->native(false),
                            Forms\Components\TextInput::make('timezone')
                                ->placeholder('Africa/Abidjan'),
                        ])
                        ->columns(2)
                        ->collapsible(),
                ])
                ->columns(2),

            Section::make('Attach offers')
                ->schema([
                    Forms\Components\Select::make('promotion_ids')
                        ->label('Promotions')
                        ->multiple()
                        ->options(fn () => Promotion::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                    Forms\Components\Select::make('coupon_ids')
                        ->label('Coupons')
                        ->multiple()
                        ->options(fn () => Coupon::query()->orderBy('code')->pluck('code', 'id'))
                        ->searchable(),
                    Forms\Components\Select::make('banner_ids')
                        ->label('Banners')
                        ->multiple()
                        ->options(fn () => StorefrontBanner::query()->orderBy('title')->pluck('title', 'id'))
                        ->searchable(),
                    Forms\Components\Select::make('collection_ids')
                        ->label('Collections')
                        ->multiple()
                        ->options(fn () => StorefrontCollection::query()->orderBy('title')->pluck('title', 'id'))
                        ->searchable(),
                    Forms\Components\Select::make('newsletter_campaign_ids')
                        ->label('Newsletter Campaigns')
                        ->multiple()
                        ->options(fn () => \App\Models\NewsletterCampaign::query()->latest()->pluck('subject', 'id'))
                        ->searchable(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => ['pending_approval', 'scheduled'],
                        'success' => ['approved', 'active'],
                        'danger' => ['rejected'],
                        'gray' => ['ended', 'draft'],
                    ])
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('priority')->sortable(),
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
            'index' => Pages\ListStorefrontCampaigns::route('/'),
            'create' => Pages\CreateStorefrontCampaign::route('/create'),
            'edit' => Pages\EditStorefrontCampaign::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    private static function localeOptions(): array
    {
        return [
            'en' => 'English',
            'fr' => 'Français',
        ];
    }
}
