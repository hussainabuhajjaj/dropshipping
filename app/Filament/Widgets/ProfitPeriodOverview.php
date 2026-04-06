<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class ProfitPeriodOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $now = now();

        $week = $this->buildPeriodStat(
            'Weekly Profit',
            $now->copy()->startOfWeek(),
            $now->copy()->endOfWeek(),
            $now->copy()->subWeek()->startOfWeek(),
            $now->copy()->subWeek()->endOfWeek(),
        );

        $month = $this->buildPeriodStat(
            'Monthly Profit',
            $now->copy()->startOfMonth(),
            $now->copy()->endOfMonth(),
            $now->copy()->subMonth()->startOfMonth(),
            $now->copy()->subMonth()->endOfMonth(),
        );

        $quarter = $this->buildPeriodStat(
            'Quarterly Profit',
            $now->copy()->startOfQuarter(),
            $now->copy()->endOfQuarter(),
            $now->copy()->subQuarter()->startOfQuarter(),
            $now->copy()->subQuarter()->endOfQuarter(),
        );

        $year = $this->buildPeriodStat(
            'Yearly Profit',
            $now->copy()->startOfYear(),
            $now->copy()->endOfYear(),
            $now->copy()->subYear()->startOfYear(),
            $now->copy()->subYear()->endOfYear(),
        );

        return [$week, $month, $quarter, $year];
    }

    private function buildPeriodStat(
        string $label,
        Carbon $start,
        Carbon $end,
        Carbon $previousStart,
        Carbon $previousEnd,
    ): Stat {
        $current = Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(grand_total), 0) as revenue, COALESCE(SUM(gross_profit_amount), 0) as profit')
            ->first();

        $previousProfit = (float) Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->sum('gross_profit_amount');

        $revenue = round((float) ($current?->revenue ?? 0), 2);
        $profit = round((float) ($current?->profit ?? 0), 2);
        $ordersCount = (int) ($current?->orders_count ?? 0);
        $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0.0;
        $change = $previousProfit != 0.0
            ? round((($profit - $previousProfit) / abs($previousProfit)) * 100, 1)
            : 0.0;

        $description = sprintf(
            'Revenue $%s | Margin %s%% | Orders %d',
            number_format($revenue, 2),
            number_format($margin, 1),
            $ordersCount,
        );

        return Stat::make($label, '$' . number_format($profit, 2))
            ->description($description)
            ->descriptionIcon($change >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->color($profit >= 0 ? 'success' : 'danger');
    }
}
