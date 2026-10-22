<?php

declare(strict_types=1);

namespace App\Filament\Resources\QrCampaignResource\Pages;

use App\Filament\Resources\QrCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListQrCampaigns extends ListRecords
{
    protected static string $resource = QrCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
