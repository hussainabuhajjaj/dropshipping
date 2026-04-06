<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ProductVariant;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class LowStockProductsTable extends BaseWidget
{
    protected static ?string $heading = 'Low Stock Products';
    protected int|string|array $columnSpan = 'full';

    public function getTableRecordKey(Model|array $record): string
    {
        if (is_array($record)) {
            return 'low-stock:' . ($record['id'] ?? md5(json_encode($record)));
        }

        return 'low-stock:' . ($record->getKey() ?? spl_object_id($record));
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        return ProductVariant::query()
            ->with('product')
            ->whereNotNull('stock_on_hand')
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('stock_on_hand', '<=', 'low_stock_threshold')
            ->whereHas('product', fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('stock_on_hand');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('product.name')->label('Product')->searchable()->limit(40),
            Tables\Columns\TextColumn::make('title')->label('Variant')->searchable()->placeholder('-'),
            Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->toggleable(),
            Tables\Columns\TextColumn::make('stock_on_hand')->label('Stock')->sortable(),
            Tables\Columns\TextColumn::make('low_stock_threshold')->label('Threshold')->sortable(),
            Tables\Columns\TextColumn::make('updated_at')->since()->label('Updated'),
        ];
    }
}
