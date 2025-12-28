<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\CouponUsageChart;
use App\Filament\Widgets\LowStockProductsTable;
use App\Filament\Widgets\OrderStatusChart;
use App\Filament\Widgets\PaymentStatusChart;
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
    protected static bool $shouldRegisterNavigation = false;

    public function getWidgets(): array
    {
        return [
            SalesTrendChart::class,
            OrderStatusChart::class,
            PaymentStatusChart::class,
            CouponUsageChart::class,
            ReturnRequestsTable::class,
            TopSellersTable::class,
            ReviewTrendChart::class,
            LowStockProductsTable::class,
        ];
    }
}
