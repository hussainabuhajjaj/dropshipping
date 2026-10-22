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
        $currentOrders = Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $revenue = Order::sumAmountInAdminCurrency('grand_total', Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
        );

        $profit = Order::sumAmountInAdminCurrency('gross_profit_amount', Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
        );

        $previousProfit = Order::sumAmountInAdminCurrency('gross_profit_amount', Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
        );

        $revenue = round($revenue, 2);
        $profit = round($profit, 2);
        $ordersCount = $currentOrders;
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
