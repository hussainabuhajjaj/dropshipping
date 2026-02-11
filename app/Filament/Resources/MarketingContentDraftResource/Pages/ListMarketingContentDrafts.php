<?php

declare(strict_types=1);

namespace App\Filament\Resources\MarketingContentDraftResource\Pages;

use App\Filament\Resources\MarketingContentDraftResource;
use Filament\Resources\Pages\ListRecords;

class ListMarketingContentDrafts extends ListRecords
{
    protected static string $resource = MarketingContentDraftResource::class;
}
