<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction as ActionsDeleteAction;
use Filament\Actions\EditAction as ActionsEditAction;
use Filament\Forms;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Str;
use UnitEnum;

class CategoryResource extends BaseResource
{
    protected static ?string $model = Category::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';

    protected static UnitEnum|string|null $navigationGroup = 'Catalog';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Category')
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\Select::make('parent_id')
                        ->relationship('parent', 'name')
                        ->label('Parent category')
                        ->searchable()
                        ->nullable()
                        ->helperText('Nest under another category to build the hierarchy.'),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->helperText('URL slug')
                        ->dehydrateStateUsing(fn ($state, callable $get) => $state ?: Str::slug($get('name'))),
                    Forms\Components\Textarea::make('description')->rows(3),
                ])
                ->columns(1),
            Section::make('Hero')
                ->schema([
                    Forms\Components\TextInput::make('hero_title')->maxLength(255),
                    Forms\Components\Textarea::make('hero_subtitle')->rows(2),
                    Forms\Components\FileUpload::make('hero_image')
                        ->disk('public')
                        ->directory('categories')
                        ->image(),
                    Forms\Components\TextInput::make('hero_cta_label')->maxLength(120),
                    Forms\Components\TextInput::make('hero_cta_link')->maxLength(255),
                ])
                ->columns(2),
            Section::make('SEO')
                ->schema([
                    Forms\Components\TextInput::make('meta_title')->label('Meta title')->maxLength(255),
                    Forms\Components\Textarea::make('meta_description')->label('Meta description')->rows(3),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('hero_image'),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('description')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('ali_category_id')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('cj_id')->searchable()->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')->label('Active')->sortable(),
                Tables\Columns\TextColumn::make('parent.name')->label('Parent')->toggleable()->searchable(),
                Tables\Columns\TextColumn::make('meta_title')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ActionsEditAction::make(),
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Category $record) => $record->slug ? route('categories.show', $record->slug) : '/products')
                    ->openUrlInNewTab(),
                ActionsDeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}

