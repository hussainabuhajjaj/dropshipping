<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;

class ProductImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    private function formSchema(): array
    {
        return [
            Forms\Components\FileUpload::make('url')
                ->label('Upload Image')
                ->image()
                ->directory('products')
                ->maxSize(5120)
                ->imageEditor()
                ->imageEditorAspectRatios([
                    null,
                    '16:9',
                    '4:3',
                    '1:1',
                ])
                ->helperText('Upload an image from your computer (max 5MB) or enter a URL below'),
            Forms\Components\TextInput::make('url')
                ->label('Or Enter Image URL')
                ->url()
                ->placeholder('https://example.com/image.jpg')
                ->helperText('Alternatively, enter a direct URL to an external image'),
            Forms\Components\TextInput::make('position')
                ->label('Display Position')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->helperText('Images are displayed in ascending order'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('url')->label('Image'),
                Tables\Columns\TextColumn::make('position')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->schema($this->formSchema()),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema($this->formSchema()),
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }
}
