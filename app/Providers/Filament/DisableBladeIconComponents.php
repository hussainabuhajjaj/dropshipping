<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\DisableBladeIconComponents as BaseDisableBladeIconComponents;

class DisableBladeIconComponents extends BaseDisableBladeIconComponents
{
    // Acts as a local alias so app-specific namespace can reference the middleware.
}
