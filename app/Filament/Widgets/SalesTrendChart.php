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

        $rows = Order::query()
            ->selectRaw('DATE(created_at) as day, SUM(grand_total) as revenue_total, SUM(gross_profit_amount) as profit_total')
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $labels = [];
        $revenueTotals = [];
        $profitTotals = [];
        $cursor = $start->copy();
        $byDay = $rows->keyBy('day');

        while ($cursor->lte(Carbon::now())) {
            $day = $cursor->toDateString();
            $labels[] = $cursor->format('M d');
            $revenueTotals[] = (float) ($byDay[$day]->revenue_total ?? 0);
            $profitTotals[] = (float) ($byDay[$day]->profit_total ?? 0);
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
