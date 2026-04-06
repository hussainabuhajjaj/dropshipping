<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\ProductReview;
use App\Models\ReturnRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $orders = Order::query();
        $paidOrders = Order::query()->where('payment_status', 'paid');

        $revenue = (float) (clone $paidOrders)->sum('grand_total');
        $grossProfit = (float) (clone $paidOrders)->sum('gross_profit_amount');
        $grossMargin = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 1) : 0.0;
        $pendingReviews = ProductReview::query()->where('status', 'pending')->count();
        $openReturns = ReturnRequest::query()->whereIn('status', ['requested', 'approved', 'received'])->count();

        return [
            Stat::make('Orders', (string) $orders->count())
                ->description('Total orders')
                ->color('primary')
                ->url(\App\Filament\Resources\OrderResource::getUrl()),
            Stat::make('Revenue', '$' . number_format($revenue, 2))
                ->description('Paid customer revenue')
                ->color('success')
                ->url(\App\Filament\Resources\OrderResource::getUrl()),
            Stat::make('Gross Profit', '$' . number_format($grossProfit, 2))
                ->description('Revenue minus supplier costs')
                ->color($grossProfit >= 0 ? 'success' : 'danger')
                ->url(\App\Filament\Resources\OrderResource::getUrl()),
            Stat::make('Gross Margin', number_format($grossMargin, 1) . '%')
                ->description('Profit / revenue')
                ->color($grossMargin >= 20 ? 'success' : ($grossMargin >= 10 ? 'warning' : 'danger')),
            Stat::make('Pending reviews', (string) $pendingReviews)
                ->description('Awaiting approval')
                ->color('warning')
                ->url(\App\Filament\Resources\ProductReviewResource::getUrl()),
            Stat::make('Open returns', (string) $openReturns)
                ->description('Need action')
                ->color('danger')
                ->url(\App\Filament\Resources\ReturnRequestResource::getUrl()),
        ];
    }
}
