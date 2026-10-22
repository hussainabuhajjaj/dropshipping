<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderAuditLogResource\Pages;

use App\Filament\Resources\OrderAuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListOrderAuditLogs extends ListRecords
{
    protected static string $resource = OrderAuditLogResource::class;
}
