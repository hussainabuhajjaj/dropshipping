<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\DispatchServingFilamentEvent as BaseDispatchServingFilamentEvent;

class DispatchServingFilamentEvent extends BaseDispatchServingFilamentEvent
{
    // Local alias so Filament expects this class.
}
