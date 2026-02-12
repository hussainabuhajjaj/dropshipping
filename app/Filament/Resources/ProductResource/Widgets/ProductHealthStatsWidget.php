<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Widgets;

use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class ProductHealthStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $baseQuery = Product::query();

        $totalProducts = (clone $baseQuery)->count();
        $activeProducts = (clone $baseQuery)->where('is_active', true)->count();

        $untranslatedProducts = (clone $baseQuery)
            ->where(function (Builder $query): void {
                $query->whereNull('translation_status')
                    ->orWhere('translation_status', 'not translated')
                    ->orWhereDoesntHave('translations');
            })
            ->count();

        $translatedProducts = max(0, $totalProducts - $untranslatedProducts);

        $marginFactor = (1 + ((float) config('pricing.min_margin_percent', 20) / 100))
            * (1 + ((float) config('pricing.shipping_buffer_percent', 10) / 100));

        $marginSetProducts = (clone $baseQuery)
            ->whereNotNull('cost_price')
            ->whereNotNull('selling_price')
            ->where('selling_price', '>', 0)
            ->whereRaw('selling_price >= (cost_price * ?)', [$marginFactor])
            ->count();

        $marginNotSetProducts = max(0, $totalProducts - $marginSetProducts);

        $zeroSellingPriceProducts = (clone $baseQuery)
            ->where(function (Builder $query): void {
                $query->whereNull('selling_price')
                    ->orWhere('selling_price', '<=', 0);
            })
            ->count();

        $variantsWithoutPriceProducts = (clone $baseQuery)
            ->whereHas('variants', function (Builder $query): void {
                $query->whereNull('price')
                    ->orWhere('price', '<=', 0);
            })
            ->count();

        $removedFromCjProducts = (clone $baseQuery)
            ->whereNotNull('cj_pid')
            ->whereNotNull('cj_removed_from_shelves_at')
            ->count();

        return [
            Stat::make('Active products', (string) $activeProducts)
                ->description("of {$totalProducts} total")
                ->color($activeProducts > 0 ? 'success' : 'gray'),
            Stat::make('Translated products', (string) $translatedProducts)
                ->description("{$untranslatedProducts} untranslated")
                ->color($translatedProducts > 0 ? 'success' : 'warning'),
            Stat::make('Untranslated products', (string) $untranslatedProducts)
                ->description('Need translation')
                ->color($untranslatedProducts > 0 ? 'warning' : 'success'),
            Stat::make('Margin set', (string) $marginSetProducts)
                ->description("{$marginNotSetProducts} not set")
                ->color($marginNotSetProducts > 0 ? 'warning' : 'success'),
            Stat::make('Selling price = 0', (string) $zeroSellingPriceProducts)
                ->description('Missing/zero product price')
                ->color($zeroSellingPriceProducts > 0 ? 'danger' : 'success'),
            Stat::make('Variants no price', (string) $variantsWithoutPriceProducts)
                ->description('Products with invalid variant prices')
                ->color($variantsWithoutPriceProducts > 0 ? 'danger' : 'success'),
            Stat::make('Removed from CJ', (string) $removedFromCjProducts)
                ->description('No longer available on CJ')
                ->color($removedFromCjProducts > 0 ? 'danger' : 'success'),
        ];
    }
}
