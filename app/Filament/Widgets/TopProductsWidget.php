<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Filament\Widgets\Concerns\AdminCurrencyConversion;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopProductsWidget extends BaseWidget
{
    use AdminCurrencyConversion;

    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $thirtyDaysAgo = now()->subDays(30);

        return $table
            ->query(
                Product::query()
                    ->select([
                        'products.id',
                        'products.name',
                    ])
                    ->selectRaw('products.selling_price * ' . $this->currencyConversionFactorSql('products.currency') . ' as selling_price')
                    ->selectRaw('COUNT(order_items.id) as total_orders')
                    ->selectRaw('SUM(order_items.quantity) as total_units')
                    ->selectRaw('SUM(' . $this->amountToUsdSql('order_items.total', 'orders.currency') . ') as total_revenue')
                    ->leftJoin('product_variants', 'products.id', '=', 'product_variants.product_id')
                    ->leftJoin('order_items', 'product_variants.id', '=', 'order_items.product_variant_id')
                    ->leftJoin('orders', function ($join) use ($thirtyDaysAgo) {
                        $join->on('order_items.order_id', '=', 'orders.id')
                            ->where('orders.payment_status', '=', 'paid')
                            ->where('orders.placed_at', '>=', $thirtyDaysAgo);
                    })
                    ->groupBy('products.id', 'products.name', 'products.selling_price', 'products.currency')
                    ->havingRaw('COUNT(order_items.id) > 0')
                    ->orderByDesc('total_revenue')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_units')
                    ->label('Units Sold')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Revenue')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Price')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->heading('Top 10 Products (Last 30 Days)')
            ->defaultSort('total_revenue', 'desc');
    }
}
