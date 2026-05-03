<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class RevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Revenue vs Profit (Last 30 Days)';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $labels = [];
        $revenue = [];
        $profit = [];

        $start = now()->subDays(29)->startOfDay();
        $end = now();
        $dailyRevenue = Order::dailySumsInAdminCurrency('grand_total', $start, $end);
        $dailyProfit = Order::dailySumsInAdminCurrency('gross_profit_amount', $start, $end);

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M d');
            $key = $date->toDateString();

            $revenue[] = (float) ($dailyRevenue[$key] ?? 0);
            $profit[] = (float) ($dailyProfit[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (USD)',
                    'data' => $revenue,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.12)',
                    'fill' => true,
                ],
                [
                    'label' => 'Gross Profit (USD)',
                    'data' => $profit,
                    'borderColor' => 'rgb(22, 163, 74)',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.12)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return "$" + value.toLocaleString(); }',
                    ],
                ],
            ],
        ];
    }
}
