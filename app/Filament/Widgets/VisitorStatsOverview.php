<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Analytics\VisitAnalyticsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VisitorStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $summary = app(VisitAnalyticsService::class)->summary();
        $active = $summary['active'] ?? [];

        return [
            Stat::make('Active Visitors', (string) ($active['total'] ?? 0))
                ->description('Last 5 minutes')
                ->color('primary'),
            Stat::make('Website Visitors', (string) ($active['website'] ?? 0))
                ->description('Live on storefront')
                ->color('info'),
            Stat::make('App Visitors', (string) ($active['app'] ?? 0))
                ->description('Live in mobile app')
                ->color('success'),
            Stat::make('Signed-in Visitors', (string) ($active['signed_in'] ?? 0))
                ->description('Authenticated sessions')
                ->color('warning'),
        ];
    }
}
