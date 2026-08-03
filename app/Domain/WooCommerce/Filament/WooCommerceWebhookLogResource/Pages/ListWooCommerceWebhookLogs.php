<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Filament\WooCommerceWebhookLogResource\Pages;

use App\Domain\WooCommerce\Filament\WooCommerceWebhookLogResource;
use Filament\Resources\Pages\ListRecords;

class ListWooCommerceWebhookLogs extends ListRecords
{
    protected static string $resource = WooCommerceWebhookLogResource::class;
}
