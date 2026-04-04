<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Today's stats
        $todayOrders = Order::whereDate('created_at', today())->count();
        $todayRevenue = Order::where('payment_status', 'paid')
            ->whereDate('created_at', today())
            ->sum('grand_total');

        // Yesterday's stats for comparison
        $yesterdayOrders = Order::whereDate('created_at', today()->subDay())->count();
        $yesterdayRevenue = Order::where('payment_status', 'paid')
            ->whereDate('created_at', today()->subDay())
            ->sum('grand_total');

        // This month
        $monthOrders = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $monthRevenue = Order::where('payment_status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('grand_total');

        // Last month for comparison
        $lastMonthOrders = Order::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
        $lastMonthRevenue = Order::where('payment_status', 'paid')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('grand_total');

        // Pending orders
        $pendingOrders = Order::where('status', 'pending')->count();

        // Calculate percentage changes
        $orderChange = $yesterdayOrders > 0 
            ? round((($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100, 1)
            : 0;
        
        $revenueChange = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : 0;

        $monthOrderChange = $lastMonthOrders > 0
            ? round((($monthOrders - $lastMonthOrders) / $lastMonthOrders) * 100, 1)
            : 0;

        return [
            Stat::make('Today\'s Orders', $todayOrders)
                ->description($orderChange >= 0 ? "+{$orderChange}% from yesterday" : "{$orderChange}% from yesterday")
                ->descriptionIcon($orderChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($orderChange >= 0 ? 'success' : 'danger'),

            Stat::make('Today\'s Revenue', '$' . number_format($todayRevenue, 2))
                ->description($revenueChange >= 0 ? "+{$revenueChange}% from yesterday" : "{$revenueChange}% from yesterday")
                ->descriptionIcon($revenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueChange >= 0 ? 'success' : 'danger'),

            Stat::make('This Month', $monthOrders . ' orders')
                ->description($monthOrderChange >= 0 ? "+{$monthOrderChange}% from last month" : "{$monthOrderChange}% from last month")
                ->descriptionIcon($monthOrderChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthOrderChange >= 0 ? 'success' : 'danger'),

            Stat::make('Pending Orders', $pendingOrders)
                ->description('Awaiting processing')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
