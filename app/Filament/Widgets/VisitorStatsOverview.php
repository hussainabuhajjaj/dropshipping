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
        $periods = $summary['periods'] ?? [];
        $today = $periods['today'] ?? [];
        $week = $periods['week'] ?? [];
        $month = $periods['month'] ?? [];

        return [
            Stat::make('Active Visitors', (string) ($active['total'] ?? 0))
                ->description('Last 5 minutes')
                ->color('primary'),
            Stat::make('Daily Visitors', (string) ($today['unique_visitors'] ?? 0))
                ->description(($today['sessions'] ?? 0) . ' sessions today')
                ->color('info'),
            Stat::make('Weekly Visitors', (string) ($week['unique_visitors'] ?? 0))
                ->description(($week['sessions'] ?? 0) . ' sessions this week')
                ->color('success'),
            Stat::make('Monthly Visitors', (string) ($month['unique_visitors'] ?? 0))
                ->description(($month['sessions'] ?? 0) . ' sessions this month')
                ->color('warning'),
        ];
    }
}
