<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SalesTrendChart extends ChartWidget
{
    protected ?string $heading = 'Revenue vs Profit (30d)';

    protected function getData(): array
    {
        $start = Carbon::now()->subDays(29)->startOfDay();
        $end = Carbon::now();

        $dailyRevenue = Order::dailySumsInAdminCurrency('grand_total', $start, $end);
        $dailyProfit = Order::dailySumsInAdminCurrency('gross_profit_amount', $start, $end);

        $labels = [];
        $revenueTotals = [];
        $profitTotals = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $day = $cursor->toDateString();
            $labels[] = $cursor->format('M d');
            $revenueTotals[] = (float) ($dailyRevenue[$day] ?? 0);
            $profitTotals[] = (float) ($dailyProfit[$day] ?? 0);
            $cursor->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $revenueTotals,
                    'backgroundColor' => 'rgba(37, 99, 235, 0.12)',
                    'borderColor' => '#2563eb',
                    'fill' => true,
                ],
                [
                    'label' => 'Gross Profit',
                    'data' => $profitTotals,
                    'backgroundColor' => 'rgba(22, 163, 74, 0.12)',
                    'borderColor' => '#16a34a',
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
}
