<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $thisWeekStart = now()->startOfWeek();
        $lastWeekStart = now()->subWeek()->startOfWeek();
        $thisMonthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonth()->startOfMonth();

        $todayRevenue = (float) Order::query()
            ->where('payment_status', 'paid')
            ->whereDate('created_at', $today)
            ->sum('grand_total');
        $todayProfit = (float) Order::query()
            ->where('payment_status', 'paid')
            ->whereDate('created_at', $today)
            ->sum('gross_profit_amount');

        $yesterdayRevenue = (float) Order::query()
            ->where('payment_status', 'paid')
            ->whereDate('created_at', $yesterday)
            ->sum('grand_total');
        $yesterdayProfit = (float) Order::query()
            ->where('payment_status', 'paid')
            ->whereDate('created_at', $yesterday)
            ->sum('gross_profit_amount');

        $todayRevenueChange = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : 0;
        $todayProfitChange = $yesterdayProfit !== 0.0
            ? round((($todayProfit - $yesterdayProfit) / abs($yesterdayProfit)) * 100, 1)
            : 0;

        $thisWeekRevenue = (float) Order::query()
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $thisWeekStart)
            ->sum('grand_total');
        $thisWeekProfit = (float) Order::query()
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $thisWeekStart)
            ->sum('gross_profit_amount');

        $lastWeekRevenue = (float) Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastWeekStart, $thisWeekStart])
            ->sum('grand_total');
        $lastWeekProfit = (float) Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastWeekStart, $thisWeekStart])
            ->sum('gross_profit_amount');

        $weekRevenueChange = $lastWeekRevenue > 0
            ? round((($thisWeekRevenue - $lastWeekRevenue) / $lastWeekRevenue) * 100, 1)
            : 0;
        $weekProfitChange = $lastWeekProfit !== 0.0
            ? round((($thisWeekProfit - $lastWeekProfit) / abs($lastWeekProfit)) * 100, 1)
            : 0;

        $thisMonthRevenue = (float) Order::query()
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $thisMonthStart)
            ->sum('grand_total');
        $thisMonthProfit = (float) Order::query()
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $thisMonthStart)
            ->sum('gross_profit_amount');

        $lastMonthRevenue = (float) Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastMonthStart, $thisMonthStart])
            ->sum('grand_total');
        $lastMonthProfit = (float) Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastMonthStart, $thisMonthStart])
            ->sum('gross_profit_amount');

        $monthRevenueChange = $lastMonthRevenue > 0
            ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : 0;
        $monthProfitChange = $lastMonthProfit !== 0.0
            ? round((($thisMonthProfit - $lastMonthProfit) / abs($lastMonthProfit)) * 100, 1)
            : 0;

        $avgOrderValue = (float) (Order::query()
            ->where('payment_status', 'paid')
            ->where('placed_at', '>=', $thisMonthStart)
            ->avg('grand_total') ?? 0);
        $monthMargin = $thisMonthRevenue > 0 ? round(($thisMonthProfit / $thisMonthRevenue) * 100, 1) : 0;

        return [
            Stat::make("Today's Revenue", '$' . number_format($todayRevenue, 2))
                ->description($todayRevenueChange >= 0 ? "+{$todayRevenueChange}% from yesterday" : "{$todayRevenueChange}% from yesterday")
                ->descriptionIcon($todayRevenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayRevenueChange >= 0 ? 'success' : 'danger')
                ->chart($this->getDailyMetricChart(7, 'grand_total')),

            Stat::make("Today's Profit", '$' . number_format($todayProfit, 2))
                ->description($todayProfitChange >= 0 ? "+{$todayProfitChange}% from yesterday" : "{$todayProfitChange}% from yesterday")
                ->descriptionIcon($todayProfitChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayProfit >= 0 ? 'success' : 'danger')
                ->chart($this->getDailyMetricChart(7, 'gross_profit_amount')),

            Stat::make('This Week Revenue', '$' . number_format($thisWeekRevenue, 2))
                ->description($weekRevenueChange >= 0 ? "+{$weekRevenueChange}% from last week" : "{$weekRevenueChange}% from last week")
                ->descriptionIcon($weekRevenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($weekRevenueChange >= 0 ? 'success' : 'danger'),

            Stat::make('This Week Profit', '$' . number_format($thisWeekProfit, 2))
                ->description($weekProfitChange >= 0 ? "+{$weekProfitChange}% from last week" : "{$weekProfitChange}% from last week")
                ->descriptionIcon($weekProfitChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($thisWeekProfit >= 0 ? 'success' : 'danger'),

            Stat::make('This Month Revenue', '$' . number_format($thisMonthRevenue, 2))
                ->description($monthRevenueChange >= 0 ? "+{$monthRevenueChange}% from last month" : "{$monthRevenueChange}% from last month")
                ->descriptionIcon($monthRevenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthRevenueChange >= 0 ? 'success' : 'danger'),

            Stat::make('This Month Profit', '$' . number_format($thisMonthProfit, 2))
                ->description($monthProfitChange >= 0 ? "+{$monthProfitChange}% from last month" : "{$monthProfitChange}% from last month")
                ->descriptionIcon($monthProfitChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($thisMonthProfit >= 0 ? 'success' : 'danger'),

            Stat::make('Gross Margin', number_format($monthMargin, 1) . '%')
                ->description('This month profit / revenue')
                ->descriptionIcon('heroicon-m-scale')
                ->color($monthMargin >= 20 ? 'success' : ($monthMargin >= 10 ? 'warning' : 'danger')),

            Stat::make('Avg Order Value', '$' . number_format($avgOrderValue, 2))
                ->description('This month')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),
        ];
    }

    private function getDailyMetricChart(int $days, string $column): array
    {
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $value = Order::query()
                ->where('payment_status', 'paid')
                ->whereDate('created_at', $date)
                ->sum($column);

            $data[] = (float) $value;
        }

        return $data;
    }
}
