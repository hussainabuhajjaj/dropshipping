<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CJCatalogStatsWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    public int $total = 0;
    public int $pageNum = 1;
    public int $totalPages = 1;
    public bool $totalPagesKnown = false;
    public ?string $avgPrice = null;
    public int $loaded = 0;
    public int $inventoryTotal = 0;
    public int $withImages = 0;
    public int $syncEnabledCount = 0;
    public int $syncDisabledCount = 0;
    public int $syncStaleCount = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Total Products', number_format($this->total))
                ->description("Page {$this->pageNum} of " . ($this->totalPagesKnown ? $this->totalPages : '--'))
                ->icon('heroicon-o-cube')
                ->color('gray'),
            Stat::make('Average Price', $this->avgPrice ? ('$' . $this->avgPrice) : '--')
                ->description('Loaded ' . number_format($this->loaded))
                ->icon('heroicon-o-currency-dollar')
                ->color('primary'),
            Stat::make('Inventory', number_format($this->inventoryTotal))
                ->description(number_format($this->withImages) . ' with images')
                ->icon('heroicon-o-archive-box')
                ->color('success'),
            Stat::make('Sync Enabled', number_format($this->syncEnabledCount))
                ->description(number_format($this->syncDisabledCount) . ' sync off')
                ->icon('heroicon-o-arrow-path')
                ->color($this->syncEnabledCount > 0 ? 'success' : 'gray'),
            Stat::make('Stale Products', number_format($this->syncStaleCount))
                ->description('Sync older than threshold')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($this->syncStaleCount > 0 ? 'warning' : 'success'),
            Stat::make('Items Loaded', number_format($this->loaded))
                ->description('Current workspace list')
                ->icon('heroicon-o-rectangle-stack')
                ->color('gray'),
        ];
    }
}
