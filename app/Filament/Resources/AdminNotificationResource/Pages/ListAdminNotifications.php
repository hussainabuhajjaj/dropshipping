<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminNotificationResource\Pages;

use App\Filament\Resources\AdminNotificationResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminNotifications extends ListRecords
{
    protected static string $resource = AdminNotificationResource::class;
}
