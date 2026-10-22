<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrderStatusChart extends ChartWidget
{
    protected ?string $heading = 'Orders by Status';

    protected function getData(): array
    {
        $statuses = [
            'pending' => 'Pending',
            'paid' => 'Paid',
            'fulfilling' => 'Fulfilling',
            'fulfilled' => 'Fulfilled',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
        ];

        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = [];
        $values = [];
        foreach ($statuses as $key => $label) {
            $labels[] = $label;
            $values[] = (int) ($counts[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $values,
                    'backgroundColor' => [
                        '#f59e0b',
                        '#22c55e',
                        '#38bdf8',
                        '#2563eb',
                        '#ef4444',
                        '#a855f7',
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
