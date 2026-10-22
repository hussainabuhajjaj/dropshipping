<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Analytics\VisitAnalyticsService;
use Filament\Widgets\ChartWidget;

class VisitorCountriesChart extends ChartWidget
{
    protected ?string $heading = 'Visitors by Country';

    protected static ?int $sort = 5;

    protected function getData(): array
    {
        $countries = app(VisitAnalyticsService::class)->summary()['geography']['top_countries'] ?? [];

        return [
            'datasets' => [[
                'label' => 'Sessions',
                'data' => array_map(fn (array $row): int => (int) $row['sessions'], $countries),
                'backgroundColor' => [
                    '#0f172a',
                    '#f97316',
                    '#f59e0b',
                    '#22c55e',
                    '#3b82f6',
                    '#8b5cf6',
                    '#ef4444',
                    '#14b8a6',
                ],
            ]],
            'labels' => array_map(
                fn (array $row): string => (string) ($row['country_code'] ? "{$row['country']} ({$row['country_code']})" : $row['country']),
                $countries
            ),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
