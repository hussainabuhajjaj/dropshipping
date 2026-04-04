<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SalesTrendChart extends ChartWidget
{
    protected ?string $heading = 'Revenue (30d)';

    protected function getData(): array
    {
        $start = Carbon::now()->subDays(29)->startOfDay();

        $rows = Order::query()
            ->selectRaw('DATE(created_at) as day, SUM(grand_total) as total')
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $labels = [];
        $totals = [];
        $cursor = $start->copy();
        $byDay = $rows->keyBy('day');

        while ($cursor->lte(Carbon::now())) {
            $day = $cursor->toDateString();
            $labels[] = $cursor->format('M d');
            $totals[] = (float) ($byDay[$day]->total ?? 0);
            $cursor->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $totals,
                    'backgroundColor' => '#2563eb',
                    'borderColor' => '#2563eb',
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
