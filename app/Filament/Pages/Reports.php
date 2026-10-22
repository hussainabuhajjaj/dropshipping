<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\CouponUsageChart;
use App\Filament\Widgets\HighestMarginProductsTable;
use App\Filament\Widgets\LowStockProductsTable;
use App\Filament\Widgets\OrderStatusChart;
use App\Filament\Widgets\PaymentStatusChart;
use App\Filament\Widgets\ProfitPeriodComparisonChart;
use App\Filament\Widgets\ProfitPeriodOverview;
use App\Filament\Widgets\ReturnRequestsTable;
use App\Filament\Widgets\ReviewTrendChart;
use App\Filament\Widgets\SalesTrendChart;
use App\Filament\Widgets\TopSellersTable;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use UnitEnum;

class Reports extends BaseDashboard
{
    protected static string $routePath = '/reports';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static UnitEnum|string|null $navigationGroup = 'Reports';
    protected static ?int $navigationSort = 10;
    protected static bool $shouldRegisterNavigation = true;

    public function getWidgets(): array
    {
        return [
            ProfitPeriodOverview::class,
            ProfitPeriodComparisonChart::class,
            SalesTrendChart::class,
            TopSellersTable::class,
            HighestMarginProductsTable::class,
            OrderStatusChart::class,
            PaymentStatusChart::class,
            CouponUsageChart::class,
            ReturnRequestsTable::class,
            ReviewTrendChart::class,
            LowStockProductsTable::class,
        ];
    }
}
