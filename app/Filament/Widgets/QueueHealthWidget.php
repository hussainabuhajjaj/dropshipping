<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class QueueHealthWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $queued = (int) DB::table('jobs')->count();
        $failed = (int) DB::table('failed_jobs')->count();

        return [
            Stat::make('Queued jobs', (string) $queued)
                ->color($queued > 50 ? 'warning' : 'primary'),
            Stat::make('Failed jobs', (string) $failed)
                ->color($failed > 0 ? 'danger' : 'success'),
        ];
    }
}
