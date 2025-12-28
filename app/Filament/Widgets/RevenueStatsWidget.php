<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

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

        // Today's revenue
        $todayRevenue = Payment::query()
            ->where('status', 'paid')
            ->whereDate('paid_at', $today)
            ->sum('amount');

        $yesterdayRevenue = Payment::query()
            ->where('status', 'paid')
            ->whereDate('paid_at', $yesterday)
            ->sum('amount');

        $todayChange = $yesterdayRevenue > 0 
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : 0;

        // This week's revenue
        $thisWeekRevenue = Payment::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', $thisWeekStart)
            ->sum('amount');

        $lastWeekRevenue = Payment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$lastWeekStart, $thisWeekStart])
            ->sum('amount');

        $weekChange = $lastWeekRevenue > 0
            ? round((($thisWeekRevenue - $lastWeekRevenue) / $lastWeekRevenue) * 100, 1)
            : 0;

        // This month's revenue
        $thisMonthRevenue = Payment::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', $thisMonthStart)
            ->sum('amount');

        $lastMonthRevenue = Payment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$lastMonthStart, $thisMonthStart])
            ->sum('amount');

        $monthChange = $lastMonthRevenue > 0
            ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : 0;

        // Average order value
        $avgOrderValue = Order::query()
            ->where('payment_status', 'paid')
            ->where('placed_at', '>=', $thisMonthStart)
            ->avg('grand_total');

        return [
            Stat::make('Today\'s Revenue', '$' . number_format($todayRevenue, 2))
                ->description($todayChange >= 0 ? "+{$todayChange}% from yesterday" : "{$todayChange}% from yesterday")
                ->descriptionIcon($todayChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayChange >= 0 ? 'success' : 'danger')
                ->chart($this->getDailyRevenueChart(7)),

            Stat::make('This Week', '$' . number_format($thisWeekRevenue, 2))
                ->description($weekChange >= 0 ? "+{$weekChange}% from last week" : "{$weekChange}% from last week")
                ->descriptionIcon($weekChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($weekChange >= 0 ? 'success' : 'danger'),

            Stat::make('This Month', '$' . number_format($thisMonthRevenue, 2))
                ->description($monthChange >= 0 ? "+{$monthChange}% from last month" : "{$monthChange}% from last month")
                ->descriptionIcon($monthChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthChange >= 0 ? 'success' : 'danger'),

            Stat::make('Avg Order Value', '$' . number_format($avgOrderValue ?? 0, 2))
                ->description('This month')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),
        ];
    }

    private function getDailyRevenueChart(int $days): array
    {
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $revenue = Payment::query()
                ->where('status', 'paid')
                ->whereDate('paid_at', $date)
                ->sum('amount');
            $data[] = (float) $revenue;
        }
        return $data;
    }
}
