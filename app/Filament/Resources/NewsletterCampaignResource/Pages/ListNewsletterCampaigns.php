<?php

declare(strict_types=1);

namespace App\Filament\Resources\NewsletterCampaignResource\Pages;

use App\Filament\Resources\NewsletterCampaignResource;
use Filament\Resources\Pages\ListRecords;

class ListNewsletterCampaigns extends ListRecords
{
    protected static string $resource = NewsletterCampaignResource::class;
}
