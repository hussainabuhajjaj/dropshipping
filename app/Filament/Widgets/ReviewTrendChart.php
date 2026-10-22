<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ProductReview;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ReviewTrendChart extends ChartWidget
{
    protected ?string $heading = 'Review Volume (30d)';

    protected function getData(): array
    {
        $start = Carbon::now()->subDays(29)->startOfDay();

        $rows = ProductReview::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $byDay = $rows->keyBy('day');
        $labels = [];
        $counts = [];
        $cursor = $start->copy();
        while ($cursor->lte(Carbon::now())) {
            $day = $cursor->toDateString();
            $labels[] = $cursor->format('M d');
            $counts[] = (int) ($byDay[$day]->total ?? 0);
            $cursor->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Reviews',
                    'data' => $counts,
                    'backgroundColor' => '#a855f7',
                    'borderColor' => '#a855f7',
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
