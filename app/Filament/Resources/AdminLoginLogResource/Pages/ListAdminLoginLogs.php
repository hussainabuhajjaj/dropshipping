<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminLoginLogResource\Pages;

use App\Filament\Resources\AdminLoginLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminLoginLogs extends ListRecords
{
    protected static string $resource = AdminLoginLogResource::class;
}
