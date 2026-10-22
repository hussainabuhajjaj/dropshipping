<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Analytics\VisitAnalyticsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class VisitorGeoMapWidget extends Widget
{
    protected string $view = 'filament.widgets.visitor-geo-map-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 4;

    protected function getViewData(): array
    {
        $data = app(VisitAnalyticsService::class)->summary();

        $geography = data_get($data, 'geography', []);

        $points = collect(data_get($geography, 'map_points', []))
            ->filter(fn ($p) => isset($p['latitude'], $p['longitude']))
            ->take(200); // 🔥 limit for performance

        $maxSessions = max(1, (int) $points->max('sessions'));

        $svgPoints = $points->map(function (array $point) use ($maxSessions): array {
            $lat = max(-85, min(85, (float) $point['latitude']));
            $lon = max(-180, min(180, (float) $point['longitude']));
            $sessions = max(0, (int) ($point['sessions'] ?? 0));

            // Normalize coordinates (simple projection)
            $x = (($lon + 180) / 360) * 1000;
            $y = ((90 - $lat) / 180) * 500;

            // Better radius scaling (log-based for UX)
            $normalized = $sessions > 0
                ? log($sessions + 1) / log($maxSessions + 1)
                : 0;

            return [
                'city' => $point['city'] ?? 'Unknown',
                'country' => $point['country'] ?? '—',
                'sessions' => $sessions,

                'x' => round($x, 2),
                'y' => round($y, 2),

                'r' => round(4 + ($normalized * 12), 2), // smoother scaling
            ];
        })->values()->all();

        return [
            'coverage' => data_get($geography, 'coverage', [
                'countries' => 0,
                'cities' => 0,
            ]),

            'topCountries' => collect(data_get($geography, 'top_countries', []))
                ->take(5)
                ->values(),

            'topCities' => collect(data_get($geography, 'top_cities', []))
                ->take(5)
                ->values(),

            'points' => $svgPoints,
            'hasData' => count($svgPoints) > 0,
        ];
    }
}
