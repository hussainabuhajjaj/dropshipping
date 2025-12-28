<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\OperationsOverview;
use App\Filament\Widgets\SalesTrendChart;
use App\Filament\Widgets\OrderStatusChart;
use App\Filament\Widgets\FulfillmentIssuesTable;
use App\Filament\Widgets\PaymentStatusChart;
use App\Filament\Widgets\TopSellersTable;
use App\Filament\Widgets\ReturnRequestsTable;
use App\Filament\Widgets\ConversionFunnelChart;
use App\Filament\Widgets\ReviewTrendChart;
use App\Filament\Widgets\CouponUsageChart;
use App\Filament\Widgets\QueueHealthWidget;
use App\Filament\Widgets\CJSyncHealthWidget;
use App\Filament\Widgets\CJWebhookHealthWidget;
use App\Filament\Widgets\LowStockProductsTable;
use App\Filament\Widgets\AnalyticsKPIWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            AdminStatsOverview::class,
            AnalyticsKPIWidget::class,
            OperationsOverview::class,
            SalesTrendChart::class,
            OrderStatusChart::class,
            PaymentStatusChart::class,
            ConversionFunnelChart::class,
            FulfillmentIssuesTable::class,
            TopSellersTable::class,
            ReturnRequestsTable::class,
            ReviewTrendChart::class,
            CouponUsageChart::class,
            QueueHealthWidget::class,
            CJSyncHealthWidget::class,
            CJWebhookHealthWidget::class,
            LowStockProductsTable::class,
        ];
    }
}
