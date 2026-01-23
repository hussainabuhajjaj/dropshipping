<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontCampaignResource\Pages;

use App\Filament\Resources\StorefrontCampaignResource;
use Filament\Resources\Pages\ListRecords;

class ListStorefrontCampaigns extends ListRecords
{
    protected static string $resource = StorefrontCampaignResource::class;
}
