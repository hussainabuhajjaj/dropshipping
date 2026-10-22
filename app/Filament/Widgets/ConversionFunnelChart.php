<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class ConversionFunnelChart extends ChartWidget
{
    protected ?string $heading = 'Order Funnel';

    protected function getData(): array
    {
        $stages = [
            'pending' => 'Pending',
            'paid' => 'Paid',
            'fulfilled' => 'Fulfilled',
        ];

        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = [];
        $values = [];
        foreach ($stages as $status => $label) {
            $labels[] = $label;
            $values[] = (int) ($counts[$status] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $values,
                    'backgroundColor' => ['#f59e0b', '#22c55e', '#2563eb'],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
