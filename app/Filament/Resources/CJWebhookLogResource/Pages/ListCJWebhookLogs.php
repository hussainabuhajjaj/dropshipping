<?php

declare(strict_types=1);

namespace App\Filament\Resources\CJWebhookLogResource\Pages;

use App\Filament\Resources\CJWebhookLogResource;
use Filament\Resources\Pages\ListRecords;

class ListCJWebhookLogs extends ListRecords
{
    protected static string $resource = CJWebhookLogResource::class;
}
