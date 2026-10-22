<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Products\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CJSyncHealthWidget extends BaseWidget
{
    protected ?string $heading = 'CJ Sync Health';
    protected ?string $description = 'Tracks CJ-enabled products and stale syncs.';

    protected function getStats(): array
    {
        $cutoff = now()->subHours(24);

        $enabled = Product::query()
            ->where('cj_sync_enabled', true)
            ->count();

        $neverSynced = Product::query()
            ->where('cj_sync_enabled', true)
            ->whereNull('cj_synced_at')
            ->count();

        $stale = Product::query()
            ->where('cj_sync_enabled', true)
            ->whereNotNull('cj_synced_at')
            ->where('cj_synced_at', '<', $cutoff)
            ->count();

        return [
            Stat::make('Sync enabled', (string) $enabled)->color('primary'),
            Stat::make('Never synced', (string) $neverSynced)->color($neverSynced > 0 ? 'warning' : 'success'),
            Stat::make('Stale > 24h', (string) $stale)->color($stale > 0 ? 'warning' : 'success'),
        ];
    }
}
