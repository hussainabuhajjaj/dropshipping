<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Products\Models\ProductVariant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $lowStock = ProductVariant::query()
            ->whereNotNull('stock_on_hand')
            ->whereNotNull('low_stock_threshold')
            ->whereRaw('stock_on_hand <= low_stock_threshold')
            ->whereRaw('stock_on_hand > 0')
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->count();

        $outOfStock = ProductVariant::query()
            ->whereNotNull('stock_on_hand')
            ->where('stock_on_hand', '<=', 0)
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->count();

        $inStock = ProductVariant::query()
            ->whereNotNull('stock_on_hand')
            ->whereNotNull('low_stock_threshold')
            ->whereRaw('stock_on_hand > low_stock_threshold')
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->count();

        return [
            Stat::make('Low Stock Items', $lowStock)
                ->description('Variants below threshold')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),

            Stat::make('Out of Stock', $outOfStock)
                ->description('Unavailable variants')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('In Stock', $inStock)
                ->description('Healthy inventory')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
