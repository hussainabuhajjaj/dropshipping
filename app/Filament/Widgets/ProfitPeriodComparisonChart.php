<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class ProfitPeriodComparisonChart extends ChartWidget
{
    protected ?string $heading = 'Revenue vs Profit by Period';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $periods = [
            'Week' => [now()->startOfWeek(), now()->endOfWeek()],
            'Month' => [now()->startOfMonth(), now()->endOfMonth()],
            'Quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'Year' => [now()->startOfYear(), now()->endOfYear()],
        ];

        $labels = [];
        $revenue = [];
        $profit = [];
        $margin = [];

        foreach ($periods as $label => [$start, $end]) {
            $periodRevenue = round(Order::sumAmountInAdminCurrency('grand_total', Order::query()
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$start, $end])
            ), 2);
            $periodProfit = round(Order::sumAmountInAdminCurrency('gross_profit_amount', Order::query()
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$start, $end])
            ), 2);

            $labels[] = $label;
            $revenue[] = $periodRevenue;
            $profit[] = $periodProfit;
            $margin[] = $periodRevenue > 0 ? round(($periodProfit / $periodRevenue) * 100, 1) : 0.0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $revenue,
                    'backgroundColor' => 'rgba(37, 99, 235, 0.6)',
                    'borderColor' => '#2563eb',
                    'type' => 'bar',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Gross Profit',
                    'data' => $profit,
                    'backgroundColor' => 'rgba(22, 163, 74, 0.6)',
                    'borderColor' => '#16a34a',
                    'type' => 'bar',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Gross Margin %',
                    'data' => $margin,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'borderColor' => '#f59e0b',
                    'type' => 'line',
                    'yAxisID' => 'margin',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'position' => 'left',
                    'ticks' => [
                        'callback' => 'function(value) { return "$" + value.toLocaleString(); }',
                    ],
                ],
                'margin' => [
                    'beginAtZero' => true,
                    'position' => 'right',
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                    'ticks' => [
                        'callback' => 'function(value) { return value + "%"; }',
                    ],
                ],
            ],
        ];
    }
}
