<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Orders\Models\OrderItem;
use App\Filament\Resources\ProductResource;
use App\Filament\Widgets\Concerns\AdminCurrencyConversion;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class HighestMarginProductsTable extends BaseWidget
{
    use AdminCurrencyConversion;

    protected static ?string $heading = 'Highest Margin Products';
    protected int|string|array $columnSpan = 'full';
    protected ?string $defaultSortColumn = 'gross_margin_percent';
    protected ?string $defaultSortDirection = 'desc';

    public function table(Table $table): Table
    {
        return $table->defaultKeySort(false);
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        $externalShippingSql = "COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(product_variants.metadata, '$.pricing_meta.external_shipping')) AS DECIMAL(12,2)), CAST(JSON_UNQUOTE(JSON_EXTRACT(product_variants.metadata, '$.external_shipping')) AS DECIMAL(12,2)), CAST(JSON_UNQUOTE(JSON_EXTRACT(products.pricing_meta, '$.external_shipping')) AS DECIMAL(12,2)), 0)";
        $orderCurrencyFactor = $this->currencyConversionFactorSql('orders.currency');
        $productCurrencyFactor = $this->currencyConversionFactorSql("COALESCE(product_variants.currency, products.currency)");
        $revenueUsdSql = $this->amountToUsdSql('order_items.total', 'orders.currency');
        $productCostUsdSql = "(order_items.quantity * COALESCE(product_variants.cost_price, products.cost_price, 0)) * {$productCurrencyFactor}";
        $externalShippingUsdSql = "(order_items.quantity * {$externalShippingSql}) * {$productCurrencyFactor}";
        $allocatedCjShippingUsdSql = "CASE WHEN COALESCE(orders.grand_total, 0) > 0 THEN (COALESCE(order_items.total, 0) / orders.grand_total) * COALESCE(orders.supplier_cj_shipping_total, 0) * {$orderCurrencyFactor} ELSE 0 END";
        $profitUsdSql = "{$revenueUsdSql} - (({$productCostUsdSql}) + ({$externalShippingUsdSql}) + ({$allocatedCjShippingUsdSql}))";

        return OrderItem::query()
            ->selectRaw("order_items.product_variant_id, SUM(order_items.quantity) as units, SUM({$revenueUsdSql}) as revenue, SUM({$profitUsdSql}) as gross_profit, CASE WHEN SUM({$revenueUsdSql}) > 0 THEN (SUM({$profitUsdSql}) / SUM({$revenueUsdSql})) * 100 ELSE 0 END as gross_margin_percent")
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->leftJoin('products', 'products.id', '=', 'product_variants.product_id')
            ->where('orders.payment_status', 'paid')
            ->whereNotNull('order_items.product_variant_id')
            ->groupBy('order_items.product_variant_id')
            ->havingRaw('SUM(order_items.total) > 0')
            ->with('productVariant.product')
            ->orderByDesc('gross_margin_percent')
            ->orderByDesc('gross_profit');
    }

    public function getTableRecordKey($record): string
    {
        return (string) ($record->product_variant_id ?? md5((string) $record->revenue . ':' . (string) $record->gross_margin_percent));
    }

    protected function getTableRecordUrl($record): ?string
    {
        $productId = $record->productVariant?->product_id;

        return $productId
            ? ProductResource::getUrl('edit', ['record' => $productId])
            : null;
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('productVariant.product.name')->label('Product')->limit(30),
            Tables\Columns\TextColumn::make('productVariant.title')->label('Variant')->limit(30),
            Tables\Columns\TextColumn::make('units')->label('Units')->sortable(),
            Tables\Columns\TextColumn::make('revenue')->label('Revenue')->money('USD')->sortable(),
            Tables\Columns\TextColumn::make('gross_profit')->label('Gross Profit')->money('USD')->sortable(),
            Tables\Columns\TextColumn::make('gross_margin_percent')->label('Margin')->suffix('%')->numeric(decimalPlaces: 1)->sortable(),
        ];
    }
}
