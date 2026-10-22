<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Widgets\ChartWidget;

class PaymentStatusChart extends ChartWidget
{
    protected ?string $heading = 'Payments by Status';

    protected function getData(): array
    {
        $statuses = ['paid', 'authorized', 'pending', 'failed', 'refunded'];
        $counts = Payment::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = [];
        $values = [];
        foreach ($statuses as $status) {
            $labels[] = ucfirst($status);
            $values[] = (int) ($counts[$status] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Payments',
                    'data' => $values,
                    'backgroundColor' => ['#22c55e', '#38bdf8', '#f59e0b', '#ef4444', '#a855f7'],
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
