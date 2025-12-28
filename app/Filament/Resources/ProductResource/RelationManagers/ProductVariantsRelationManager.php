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

class ProductVariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    private function formSchema(): array
    {
        return [
            Forms\Components\TextInput::make('cj_vid')->label('CJ VID'),
            Forms\Components\TextInput::make('sku')->required(),
            Forms\Components\TextInput::make('title')->required(),
            Forms\Components\TextInput::make('price')
                ->label('Variant price')
                ->helperText('Overrides product price for this variant.')
                ->numeric()
                ->required(),
            Forms\Components\TextInput::make('compare_at_price')
                ->label('Compare-at price')
                ->helperText('Optional MSRP or crossed-out price.')
                ->numeric(),
            Forms\Components\TextInput::make('currency')->default('USD')->required(),
            Forms\Components\Select::make('inventory_policy')
                ->options(['allow' => 'Allow', 'deny' => 'Deny'])
                ->default('allow')
                ->required(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('cj_vid')
                    ->label('CJ VID')
                    ->copyable()
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('title')->label('Title')->searchable(),
                Tables\Columns\TextColumn::make('price')->money(fn ($record) => $record->currency)->sortable(),
                Tables\Columns\TextColumn::make('compare_at_price')->money(fn ($record) => $record->currency)->toggleable(),
                Tables\Columns\TextColumn::make('inventory_policy')->badge(),
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
