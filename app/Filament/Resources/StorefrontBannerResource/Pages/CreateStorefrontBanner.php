<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontBannerResource\Pages;

use App\Filament\Resources\StorefrontBannerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStorefrontBanner extends CreateRecord
{
    protected static string $resource = StorefrontBannerResource::class;
}
