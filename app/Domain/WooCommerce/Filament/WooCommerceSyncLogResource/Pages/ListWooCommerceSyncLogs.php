<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Filament\WooCommerceSyncLogResource\Pages;

use App\Domain\WooCommerce\Filament\WooCommerceSyncLogResource;
use Filament\Resources\Pages\ListRecords;

class ListWooCommerceSyncLogs extends ListRecords
{
    protected static string $resource = WooCommerceSyncLogResource::class;
}
