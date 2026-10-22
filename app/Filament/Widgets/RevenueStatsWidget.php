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

        $todayRevenue = Order::sumAmountInAdminCurrency('grand_total', Order::query()
            ->where('payment_status', 'paid')
            ->whereDate('created_at', $today)
        );
        $todayProfit = Order::sumAmountInAdminCurrency('gross_profit_amount', Order::query()
            ->where('payment_status', 'paid')
            ->whereDate('created_at', $today)
        );

        $yesterdayRevenue = Order::sumAmountInAdminCurrency('grand_total', Order::query()
            ->where('payment_status', 'paid')
            ->whereDate('created_at', $yesterday)
        );
        $yesterdayProfit = Order::sumAmountInAdminCurrency('gross_profit_amount', Order::query()
            ->where('payment_status', 'paid')
            ->whereDate('created_at', $yesterday)
        );

        $todayRevenueChange = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : 0;
        $todayProfitChange = $yesterdayProfit !== 0.0
            ? round((($todayProfit - $yesterdayProfit) / abs($yesterdayProfit)) * 100, 1)
            : 0;

        $thisWeekRevenue = Order::sumAmountInAdminCurrency('grand_total', Order::query()
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $thisWeekStart)
        );
        $thisWeekProfit = Order::sumAmountInAdminCurrency('gross_profit_amount', Order::query()
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $thisWeekStart)
        );

        $lastWeekRevenue = Order::sumAmountInAdminCurrency('grand_total', Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastWeekStart, $thisWeekStart])
        );
        $lastWeekProfit = Order::sumAmountInAdminCurrency('gross_profit_amount', Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastWeekStart, $thisWeekStart])
        );

        $weekRevenueChange = $lastWeekRevenue > 0
            ? round((($thisWeekRevenue - $lastWeekRevenue) / $lastWeekRevenue) * 100, 1)
            : 0;
        $weekProfitChange = $lastWeekProfit !== 0.0
            ? round((($thisWeekProfit - $lastWeekProfit) / abs($lastWeekProfit)) * 100, 1)
            : 0;

        $thisMonthRevenue = Order::sumAmountInAdminCurrency('grand_total', Order::query()
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $thisMonthStart)
        );
        $thisMonthProfit = Order::sumAmountInAdminCurrency('gross_profit_amount', Order::query()
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $thisMonthStart)
        );

        $lastMonthRevenue = Order::sumAmountInAdminCurrency('grand_total', Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastMonthStart, $thisMonthStart])
        );
        $lastMonthProfit = Order::sumAmountInAdminCurrency('gross_profit_amount', Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastMonthStart, $thisMonthStart])
        );

        $monthRevenueChange = $lastMonthRevenue > 0
            ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : 0;
        $monthProfitChange = $lastMonthProfit !== 0.0
            ? round((($thisMonthProfit - $lastMonthProfit) / abs($lastMonthProfit)) * 100, 1)
            : 0;

        $paidOrdersThisMonth = Order::query()
            ->where('payment_status', 'paid')
            ->where('placed_at', '>=', $thisMonthStart)
            ->count();

        $totalRevenueThisMonth = Order::sumAmountInAdminCurrency('grand_total', Order::query()
            ->where('payment_status', 'paid')
            ->where('placed_at', '>=', $thisMonthStart)
        );

        $avgOrderValue = $paidOrdersThisMonth > 0 ? round($totalRevenueThisMonth / $paidOrdersThisMonth, 2) : 0;
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
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now();
        $dailyTotals = Order::dailySumsInAdminCurrency($column, $start, $end);

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $data[] = (float) ($dailyTotals[$date->toDateString()] ?? 0);
        }

        return $data;
    }
}
