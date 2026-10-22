<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProductReviewResource\Pages;
use App\Models\ProductReview;
use BackedEnum;
use Filament\Actions\DeleteAction as ActionsDeleteAction;
use Filament\Actions\EditAction as ActionsEditAction;
use Filament\Forms;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

class ProductReviewResource extends BaseResource
{
    protected static ?string $model = ProductReview::class;

       protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-star';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Review')
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->relationship('product', 'name')
                        ->searchable()
                        ->required(),
                    Forms\Components\Select::make('customer_id')
                        ->relationship('customer', 'email')
                        ->searchable()
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('rating')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(5)
                        ->required(),
                    Forms\Components\TextInput::make('title')->maxLength(120),
                    Forms\Components\Textarea::make('body')->rows(4)->required(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Product')->searchable(),
                Tables\Columns\TextColumn::make('customer.email')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('rating')->sortable(),
                IconColumn::make('verified_purchase')
                    ->label('Verified')
                    ->boolean(),
                Tables\Columns\TextColumn::make('helpful_count')
                    ->label('Helpful')
                    ->alignCenter()
                    ->sortable(),
                ImageColumn::make('images')
                    ->label('Images')
                    ->getStateUsing(fn ($record) => $record->images[0] ?? null)
                    ->size(40)
                    ->circular(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
                TernaryFilter::make('verified_purchase')->label('Verified purchase'),
            ])
            ->recordActions([
                ActionsEditAction::make(),
                ActionsDeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Approve selected')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => ProductReview::whereIn('id', $records)->update(['status' => 'approved'])),
                    BulkAction::make('reject')
                        ->label('Reject selected')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => ProductReview::whereIn('id', $records)->update(['status' => 'rejected'])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductReviews::route('/'),
            'create' => Pages\CreateProductReview::route('/create'),
            'edit' => Pages\EditProductReview::route('/{record}/edit'),
        ];
    }
}


