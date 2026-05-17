<?php

declare(strict_types=1);

namespace App\Filament\Resources\WhatsAppOrderIntentResource\Pages;

use App\Filament\Resources\WhatsAppOrderIntentResource;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppOrderIntents extends ListRecords
{
    protected static string $resource = WhatsAppOrderIntentResource::class;
}
