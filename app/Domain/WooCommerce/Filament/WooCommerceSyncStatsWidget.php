<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Filament;

use App\Domain\WooCommerce\Models\WooCommerceProductMap;
use App\Domain\WooCommerce\Models\WooCommerceOrderMap;
use App\Domain\WooCommerce\Models\WooCommerceSyncLog;
use Filament\Widgets\Widget;

class WooCommerceSyncStatsWidget extends Widget
{
    protected string $view = 'filament.widgets.woocommerce-sync-stats';

    public function getViewData(): array
    {
        return [
            'products_synced' => WooCommerceProductMap::query()->where('status', 'synced')->count(),
            'products_failed' => WooCommerceProductMap::query()->where('status', 'failed')->count(),
            'orders_synced' => WooCommerceOrderMap::query()->where('status', 'synced')->count(),
            'orders_failed' => WooCommerceOrderMap::query()->where('status', 'failed')->count(),
            'recent_syncs' => WooCommerceSyncLog::query()
                ->where('type', 'woocommerce')
                ->latest()
                ->limit(5)
                ->get(),
        ];
    }
}
