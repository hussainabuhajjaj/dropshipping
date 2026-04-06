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
        $revenue = [];
        $profit = [];
        $labels = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M d');

            $dailyRevenue = (float) Order::query()
                ->where('payment_status', 'paid')
                ->whereDate('created_at', $date)
                ->sum('grand_total');
            $dailyProfit = (float) Order::query()
                ->where('payment_status', 'paid')
                ->whereDate('created_at', $date)
                ->sum('gross_profit_amount');

            $revenue[] = $dailyRevenue;
            $profit[] = $dailyProfit;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue ($)',
                    'data' => $revenue,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.12)',
                    'fill' => true,
                ],
                [
                    'label' => 'Gross Profit ($)',
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
